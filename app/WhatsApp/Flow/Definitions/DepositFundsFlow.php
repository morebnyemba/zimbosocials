<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
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
            'awaiting_payment' => $this->awaitingPayment($input, $ctx),
            'awaiting_sender_name' => $this->awaitingSenderName($input, $ctx),
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
        $sections = [];
        $gatewayOn = (bool) config('services.deposits.gateway_enabled', false);

        // ⚡ Instant / express — auto-confirming Paynow methods. Off by default:
        // deposits are taken manually and verified by an admin (see the config).
        if ($gatewayOn) {
            $instantRows = [];
            foreach (self::MENU as $key) {
                $i++;
                $instantRows[] = ['id' => 'fs:'.$i, 'title' => PaynowMobileService::PROVIDERS[$key]['label']];
                $map[$i] = 'g:'.$key;
            }
            $sections[] = ['title' => '⚡ Instant', 'rows' => $instantRows];
        }

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

        // Card / anything else → the secure wallet page. Only when the gateway
        // is on; that page is a card checkout, so offering it while payments
        // are manual-only would send people somewhere that can't take money.
        if ($gatewayOn) {
            $i++;
            $sections[] = ['title' => 'Other', 'rows' => [
                ['id' => 'fs:'.$i, 'title' => 'Card / Other', 'description' => 'Pay on the website'],
            ]];
            $map[$i] = 'card';
        }

        if ($sections === []) {
            return FlowResult::fail('No payment methods are set up right now — please contact *support* and we\'ll sort it out.');
        }

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

        // Gateway express → collect the phone to charge. Re-checked here, not
        // just when building the menu: a customer can tap a row from a list we
        // sent before the gateway was switched off.
        if (! config('services.deposits.gateway_enabled', false)) {
            return $this->methodMenu((float) $ctx->get('deposit_amount', 0), $ctx);
        }

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

        $transaction = Transaction::create([
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
        // Lead with the chat. They have just paid, they are holding their phone
        // with the confirmation on screen, and sending a photo here attaches it
        // to this deposit automatically (see ProofIntake). Sending them to a
        // website to log in and upload is friction at the one moment we cannot
        // afford any — the site link stays as a fallback, not the instruction.
        $lines[] = '';
        $lines[] = '📸 *Once you\'ve paid, send the screenshot right here in this chat.*';
        $lines[] = 'No screenshot? Just reply *done* and I\'ll take the details instead.';

        // Stay in the flow rather than completing: if they come back with
        // "done" instead of a screenshot we still want the sender's name, which
        // is what an admin matches against the statement.
        $ctx->set('deposit_transaction_id', $transaction->id);

        return FlowResult::step(implode("\n", $lines), 'awaiting_payment');
    }

    /**
     * They've paid but sent no screenshot. The sender's name is what an admin
     * matches against the EcoCash/InnBucks statement, so it is worth one
     * question — without it a payment can sit unmatched for hours.
     */
    private function awaitingPayment(string $input, SessionContext $ctx): FlowResult
    {
        $said = mb_strtolower(trim($input));

        $paid = (bool) preg_match('/\b(done|paid|finished|sent|ndatuma|ndabhadhara|complete|ok)\b/i', $said);
        if (! $paid) {
            return FlowResult::retry(
                "No rush 👍 Send the *screenshot* here when you've paid, or reply *done* and I'll take the details instead.",
                'awaiting_payment'
            );
        }

        return FlowResult::step(
            "👍 Thanks! What *name* is on the transaction — the name the money was sent from?\n\n"
            .'That\'s how our team matches your payment on the statement.',
            'awaiting_sender_name'
        );
    }

    /**
     * Record who paid, and hand it to an admin. Deliberately does NOT credit
     * anything: a name is a claim, not proof, and the balance only moves once a
     * human has matched it against the statement.
     */
    private function awaitingSenderName(string $input, SessionContext $ctx): FlowResult
    {
        $name = trim($input);

        if (mb_strlen($name) < 3 || ! preg_match('/\p{L}{2,}/u', $name)) {
            return FlowResult::retry(
                'Sorry, I need the *name* on the transaction — the name the money was sent from. 🙏',
                'awaiting_sender_name'
            );
        }

        $transaction = Transaction::find((int) $ctx->get('deposit_transaction_id'));
        if (! $transaction) {
            return FlowResult::fail('I couldn\'t find that deposit — reply *deposit* to start again, or type *support* for help.');
        }

        $name = mb_substr($name, 0, 80);
        $transaction->update([
            'notes' => "Paid via WhatsApp — sender name: {$name}. Awaiting admin verification (no screenshot sent).",
        ]);

        $this->notifyAdmins($transaction, $name);

        return FlowResult::complete(
            "✅ Got it — thanks *{$name}*.\n\n"
            ."Our team is checking the payment against the statement now and will credit your wallet as soon as it's confirmed. "
            ."You'll get a message here the moment it's done. 🙏\n\n"
            .'_If you have the screenshot, sending it here speeds this up a lot._'
        );
    }

    /** Money is waiting on a human — tell them, with a way to reply. */
    private function notifyAdmins(Transaction $transaction, string $senderName): void
    {
        try {
            $user = User::find($transaction->user_id);
            $amount = number_format((float) abs($transaction->amount), 2);
            $cur = $user?->currency ?? 'USD';

            \App\Services\NotificationService::notifyAdmins(
                'admin_deposit_proof',
                'Deposit claimed (no screenshot)',
                ($user?->name ?? 'A customer')." says they've paid {$amount} {$cur} — sender name *{$senderName}*, deposit #{$transaction->id}. "
                ."Check the statement and credit it in Transactions.\n\nReply *RECEIVED* to confirm you've got this.",
                [
                    'transaction_id' => (int) $transaction->id,
                    'user_name' => $user?->name,
                    'amount' => $amount,
                    'sender_name' => $senderName,
                    'source' => 'whatsapp-declared',
                ],
            );
        } catch (\Throwable $e) {
            // The claim is already recorded; a failed alert must not lose it.
            \Illuminate\Support\Facades\Log::warning('Deposit claim admin-notify failed', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);
        }
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
