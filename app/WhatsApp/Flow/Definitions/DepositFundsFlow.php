<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Services\DepositService;
use App\Services\Paynow\PaynowMobileService;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Messaging\ResponseBuilder;
use App\WhatsApp\Session\SessionContext;

/**
 * Deposit funds via Paynow Express Checkout, in chat. Flow id: 'deposit'.
 *   ask_amount → choose_method → ask_phone → confirm → initiate
 *   OMari also has → ask_otp
 * Cards / other methods hand off to the secure wallet page. Crediting happens
 * via the Paynow webhook once the customer approves. No PINs/OTPs are stored.
 */
class DepositFundsFlow extends AbstractFlow
{
    /** Menu order for the mobile-money providers. */
    private const MENU = ['ecocash', 'onemoney', 'innbucks', 'omari'];

    public function __construct(
        ResponseBuilder $rb,
        private readonly PaynowMobileService $paynow,
        private readonly DepositService $deposits,
    ) {
        parent::__construct($rb);
    }

    public function id(): string
    {
        return 'deposit';
    }

    /** Loose user/AI wording → provider key. */
    private const METHOD_ALIASES = [
        'ecocash' => 'ecocash', 'eco' => 'ecocash',
        'onemoney' => 'onemoney', 'one money' => 'onemoney', 'netone' => 'onemoney',
        'innbucks' => 'innbucks', 'inn bucks' => 'innbucks',
        'omari' => 'omari', "o'mari" => 'omari', 'o mari' => 'omari',
    ];

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        // AI fast-forward: consume whatever was extracted (amount, method,
        // phone) and jump to the first missing step — stopping at confirm;
        // the payment request is only ever sent on the user's explicit yes.
        $amount = (float) preg_replace('/[^0-9.]/', '', (string) $ctx->pullPrefill('amount'));
        if ($amount >= 1 && $amount <= 10000) {
            $ctx->set('deposit_amount', $amount);
        }

        $method = mb_strtolower(trim((string) $ctx->pullPrefill('method')));
        if ($method !== '' && isset(self::METHOD_ALIASES[$method])) {
            $ctx->set('deposit_provider', self::METHOD_ALIASES[$method]);
        }

        $phonePrefill = trim((string) $ctx->pullPrefill('phone'));
        if ($phonePrefill !== '' && ($normalized = $this->paynow->normalizeZwPhone($phonePrefill)) !== null) {
            $ctx->set('deposit_phone', $normalized);
        }

        $knownAmount = (float) $ctx->get('deposit_amount', 0);
        if ($knownAmount < 1) {
            return FlowResult::step("➕ *Add funds*\n\nHow much would you like to deposit? (enter an amount)", 'ask_amount');
        }
        if (! $ctx->has('deposit_provider')) {
            return $this->methodMenu($knownAmount, $ctx);
        }
        if (! $ctx->has('deposit_phone')) {
            return $this->askPhonePrompt((string) $ctx->get('deposit_provider'), $ctx);
        }

        return $this->confirmPrompt($ctx);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return match ($state) {
            'choose_method' => $this->chooseMethod($input, $ctx),
            'ask_phone' => $this->askPhone($input, $ctx),
            'confirm' => $this->confirm($input, $ctx),
            'ask_otp' => $this->askOtp($input, $ctx),
            default => $this->askAmount($input, $ctx),
        };
    }

    private function askAmount(string $input, SessionContext $ctx): FlowResult
    {
        // This step must only consume a message that IS an amount. Blindly
        // stripping non-digits turned a sentence like "i want 3k followers on
        // instagram" into a $3 deposit — the customer's actual intent (an order)
        // was lost and money was asked for. Anything that isn't a bare amount
        // retries, which hands it to the AI to re-route (e.g. into an order).
        if (! preg_match('/^\s*(?:\$|usd\s*)?\s*(\d{1,6}(?:[.,]\d{1,2})?)\s*(?:usd|dollars?)?\s*$/i', trim($input), $m)) {
            return FlowResult::retry(
                'How much would you like to deposit? Send just the amount (e.g. *5*), or type *cancel*.',
                'ask_amount'
            );
        }

        $amount = (float) str_replace(',', '.', $m[1]);

        if ($amount < 1) {
            return FlowResult::retry('Please enter an amount of at least 1, or type *cancel*.', 'ask_amount');
        }
        if ($amount > 10000) {
            return FlowResult::retry('Maximum deposit is 10,000. Enter a smaller amount, or type *cancel*.', 'ask_amount');
        }
        $ctx->set('deposit_amount', $amount);

        return $this->methodMenu($amount, $ctx);
    }

    private function methodMenu(float $amount, SessionContext $ctx): FlowResult
    {
        $cur = $this->user($ctx)?->currency ?? 'USD';
        $bonus = $this->deposits->manualDepositBonusPercent();

        // We build the visible rows AND a parallel index→option map so the tap
        // (or typed number) resolves unambiguously — gateway keys and manual
        // method_keys can overlap (e.g. an "EcoCash" express AND an "EcoCash USD"
        // manual account), so we route by position, not by label.
        $map = [];
        $i = 0;

        // ⚡ Instant / express — auto-confirming Paynow methods.
        $instantRows = [];
        foreach (self::MENU as $key) {
            $i++;
            $instantRows[] = ['id' => 'fs:'.$i, 'title' => PaynowMobileService::PROVIDERS[$key]['label']];
            $map[$i] = 'g:'.$key;
        }

        $sections = [['title' => '⚡ Instant', 'rows' => $instantRows]];

        // 🏦 Manual transfers — pay to our account, upload proof; these earn the
        // deposit bonus, so surface that right in the row.
        $manuals = ManualPaymentDetail::active()->whereNull('gateway_type')->ordered()->get();
        if ($manuals->isNotEmpty()) {
            $manualRows = [];
            foreach ($manuals as $m) {
                $i++;
                $manualRows[] = [
                    'id' => 'fs:'.$i,
                    'title' => $m->label,
                    'description' => $bonus > 0 ? '+'.$this->trimPercent($bonus).'% bonus' : 'Pay & upload proof',
                ];
                $map[$i] = 'm:'.$m->id;
            }
            $sections[] = ['title' => '🏦 Manual (+'.$this->trimPercent($bonus).'% bonus)', 'rows' => $manualRows];
        }

        // Card / anything else → the secure wallet page.
        $i++;
        $sections[] = ['title' => 'Other', 'rows' => [
            ['id' => 'fs:'.$i, 'title' => 'Card / Other', 'description' => 'Pay on the website'],
        ]];
        $map[$i] = 'card';

        $ctx->set('deposit_method_map', $map);

        $body = '💳 Deposit *'.$this->money($amount, $cur).'* — how would you like to pay?';
        if ($bonus > 0 && $manuals->isNotEmpty()) {
            $body .= "\n\n💵 *Manual* transfers earn a *+".$this->trimPercent($bonus).'% bonus* on your deposit!';
        }

        return FlowResult::step($body, 'choose_method')
            ->withList('Payment method', $sections, 'Add funds');
    }

    private function chooseMethod(string $input, SessionContext $ctx): FlowResult
    {
        $map = (array) $ctx->get('deposit_method_map', []);
        $idx = (int) preg_replace('/\D+/', '', $input);
        $token = $map[$idx] ?? null;

        if ($token === null) {
            return FlowResult::retry('Please reply with a valid number, or type *cancel*.', 'choose_method');
        }

        // Card / Other → website.
        if ($token === 'card') {
            return FlowResult::complete(
                "💳 To pay by *card or another method*, open your wallet:\n".url('/wallet')
                ."\n\nYour balance updates automatically once payment is confirmed."
            );
        }

        // Manual transfer → show our account details, open a pending deposit.
        if (str_starts_with($token, 'm:')) {
            return $this->chooseManual((int) substr($token, 2), $ctx);
        }

        // Gateway express → collect the phone to charge.
        $provider = substr($token, 2);
        $ctx->set('deposit_provider', $provider);

        return $this->askPhonePrompt($provider, $ctx);
    }

    /**
     * Manual bank/wallet transfer: create the pending deposit (awaiting proof)
     * and hand back the pay-to details, the bonus, and how to submit proof.
     * No money moves here — an admin credits it (plus bonus) once proof lands.
     */
    private function chooseManual(int $detailId, SessionContext $ctx): FlowResult
    {
        $detail = ManualPaymentDetail::active()->whereNull('gateway_type')->find($detailId);
        if (! $detail) {
            return FlowResult::retry('That method is no longer available — pick another, or type *cancel*.', 'choose_method');
        }

        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail('Please try again from the *menu*.');
        }

        // Cap open manual requests (same guard as the wallet page).
        $open = Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')->where('status', 'pending')
            ->where('method', $detail->method_key)->count();
        if ($open >= 3) {
            return FlowResult::fail('You already have a few manual deposits awaiting proof. Upload proof for those first at '.url('/wallet').', then start another.');
        }

        $amount = (float) $ctx->get('deposit_amount', 0);
        $cur = $user->currency ?? 'USD';

        Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $amount,
            'balance_before' => $user->balance,
            'balance_after' => $user->balance, // not credited — awaiting proof
            'method' => $detail->method_key,
            'status' => 'pending',
            'notes' => 'Awaiting proof of payment (via WhatsApp)',
        ]);

        $bonus = $this->deposits->manualDepositBonusPercent();
        $lines = [
            '🏦 *'.$detail->label.'*',
            '',
            'Please send *'.$this->money($amount, $cur).'* to:',
        ];
        if ($detail->account_name) {
            $lines[] = '• Name: *'.$detail->account_name.'*';
        }
        if ($detail->account_number) {
            $lines[] = '• Account: *'.$detail->account_number.'*';
        }
        if ($detail->instructions) {
            $lines[] = '';
            $lines[] = $detail->instructions;
        }
        if ($bonus > 0) {
            $bonusAmt = $this->money(round($amount * $bonus / 100, 2), $cur);
            $lines[] = '';
            $lines[] = "💵 You'll earn a *+".$this->trimPercent($bonus).'% bonus* ('.$bonusAmt.') once approved!';
        }
        $lines[] = '';
        $lines[] = '📸 After paying, upload your proof of payment here to get credited:';
        $lines[] = url('/wallet');

        return FlowResult::complete(implode("\n", $lines));
    }

    /** 5.00 → "5", 7.50 → "7.5" for clean bonus copy. */
    private function trimPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }

    private function askPhonePrompt(string $provider, SessionContext $ctx): FlowResult
    {
        $label = PaynowMobileService::PROVIDERS[$provider]['label'];
        $default = $this->paynow->normalizeZwPhone($ctx->phone);

        $res = FlowResult::step(
            "📱 Enter the *{$label}* mobile number to charge (e.g. 0771234567)."
            .($default ? "\n\nOr use your WhatsApp number *{$default}*:" : ''),
            'ask_phone'
        );

        return $default
            ? $res->withButtons([['id' => 'fs:ok', 'title' => 'Use my number']])
            : $res;
    }

    private function askPhone(string $input, SessionContext $ctx): FlowResult
    {
        $default = $this->paynow->normalizeZwPhone($ctx->phone);
        $phone = ($default && $this->meansUseMyNumber($input))
            ? $default
            : $this->paynow->normalizeZwPhone($input);

        if ($phone === null) {
            return FlowResult::retry("That doesn't look like a valid number. Enter it as *0771234567*, or type *cancel*.", 'ask_phone');
        }

        $ctx->set('deposit_phone', $phone);

        return $this->confirmPrompt($ctx);
    }

    /**
     * "Use my WhatsApp number" said in words rather than tapped. Customers reply
     * "okay i will use it", "yes use my number", "ehe" — all of which used to
     * fall through as an invalid phone and stall the deposit. Any message with a
     * digit is treated as an actual number instead, so "yes 0771234567" still
     * uses the number they typed.
     */
    private function meansUseMyNumber(string $input): bool
    {
        $t = mb_strtolower(trim($input));

        if ($t === '' || preg_match('/\d/', $t)) {
            return false;
        }

        if (in_array($t, ['ok', 'okay', 'yes', 'yeah', 'yep', 'sure', 'yebo', 'ehe', 'hongu', 'ndozvo', 'same one', 'this one'], true)) {
            return true;
        }

        // "use it", "use my number", "ndoshandisa iyi", "shandisa nhamba yangu"
        if (preg_match('/\b(use|shandisa|ndoshandisa)\b/u', $t) && preg_match('/\b(it|my|number|nhamba|yangu|iyi|iyoyo)\b/u', $t)) {
            return true;
        }

        // Leading affirmative: "okay i will use it", "yes please", "sure thing"
        return (bool) preg_match('/^(ok(ay)?|yes|yeah|yep|sure|yebo|ehe|hongu)\b/u', $t);
    }

    private function confirmPrompt(SessionContext $ctx): FlowResult
    {
        $amount = (float) $ctx->get('deposit_amount', 0);
        $phone = (string) $ctx->get('deposit_phone');
        $cur = $this->user($ctx)?->currency ?? 'USD';
        $label = PaynowMobileService::PROVIDERS[$ctx->get('deposit_provider')]['label'];

        return FlowResult::step(
            "🧾 *Confirm*\n\nAmount: *".$this->money($amount, $cur)."*\nMethod: *{$label}*\nNumber: *{$phone}*\n\nSend the payment request?",
            'confirm'
        )->withButtons([
            ['id' => 'fs:yes', 'title' => '✅ Send request'],
            ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
        ]);
    }

    private function confirm(string $input, SessionContext $ctx): FlowResult
    {
        $t = mb_strtolower(trim($input));

        if (! in_array($t, ['yes', 'y', 'send', 'ok', 'confirm'], true)) {
            // Only an explicit "no" cancels; anything else (e.g. "use $50
            // instead") is handed to the AI, which can adjust and re-confirm.
            if (in_array($t, ['no', 'n', 'cancel', 'stop', 'kwete', 'hatshi'], true)) {
                return FlowResult::fail('No problem — deposit cancelled. Type *deposit* to start again.');
            }

            return FlowResult::retry('Tap *✅ Send request* (or reply *YES*) to confirm — or *✖ Cancel* to stop.', 'confirm')
                ->withButtons([
                    ['id' => 'fs:yes', 'title' => '✅ Send request'],
                    ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
                ]);
        }

        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail('Please try again from the *menu*.');
        }

        $res = $this->paynow->initiate(
            $user,
            (string) $ctx->get('deposit_provider'),
            (string) $ctx->get('deposit_phone'),
            (float) $ctx->get('deposit_amount'),
        );

        if (empty($res['ok'])) {
            return FlowResult::fail('⚠️ '.($res['message'] ?? 'Could not start the payment.').' Type *deposit* to try again.');
        }

        $phone = $ctx->get('deposit_phone');

        return match ($res['flow']) {
            'omari_otp' => tap(
                FlowResult::step("🔐 ".($res['message'] ?? 'Enter the OTP sent to your phone.')."\n\nEnter the *OTP* here:", 'ask_otp'),
                fn () => $ctx->set('deposit_txn_id', $res['transaction_id']),
            ),
            'innbucks_authcode' => FlowResult::complete(
                "🟣 *InnBucks payment*\n\nOpen InnBucks and pay using code *{$res['authorization_code']}*"
                .($res['instructions'] ? "\n\n{$res['instructions']}" : '')
                ."\n\nYour balance updates automatically once confirmed. Type *balance* to check."
            ),
            default => FlowResult::complete(
                "📲 Payment request sent to *{$phone}*.\n\n".($res['message'] ?? 'Approve it on your phone.')
                ."\n\nYour balance updates automatically once approved — type *balance* to check. 💰"
            ),
        };
    }

    private function askOtp(string $input, SessionContext $ctx): FlowResult
    {
        $txnId = (int) $ctx->get('deposit_txn_id');
        $txn = $txnId ? Transaction::find($txnId) : null;
        if (! $txn) {
            return FlowResult::fail('Something went wrong. Type *deposit* to start again.');
        }

        $otp = preg_replace('/\D+/', '', $input);
        if (mb_strlen($otp) < 4) {
            return FlowResult::step('Please enter the numeric OTP, or type *cancel*.', 'ask_otp');
        }

        $res = $this->paynow->submitOtp($txn, $otp);
        if (empty($res['ok'])) {
            return FlowResult::step('❌ '.$res['message']."\n\nTry again, or type *cancel*.", 'ask_otp');
        }

        return FlowResult::complete(
            "✅ ".$res['message']."\n\nYour balance updates automatically once confirmed — type *balance* to check. 💰"
        );
    }
}
