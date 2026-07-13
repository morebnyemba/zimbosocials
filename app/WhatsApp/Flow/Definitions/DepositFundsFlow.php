<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Transaction;
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

    public function __construct(ResponseBuilder $rb, private readonly PaynowMobileService $paynow)
    {
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
        $amount = (float) preg_replace('/[^0-9.]/', '', $input);
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
        $rows = [];
        $i = 1;
        foreach (self::MENU as $key) {
            $rows[] = ['id' => 'fs:'.$i, 'title' => PaynowMobileService::PROVIDERS[$key]['label']];
            $i++;
        }
        $rows[] = ['id' => 'fs:'.$i, 'title' => 'Card / Other', 'description' => 'Pay on the website'];

        return FlowResult::step('💳 Deposit *'.$this->money($amount, $cur).'* — how would you like to pay?', 'choose_method')
            ->withList('Payment method', [['title' => 'Pay with', 'rows' => $rows]], 'Add funds');
    }

    private function chooseMethod(string $input, SessionContext $ctx): FlowResult
    {
        $idx = (int) preg_replace('/\D+/', '', $input) - 1;

        // Last option = Card / Other → website.
        if ($idx === count(self::MENU)) {
            return FlowResult::complete(
                "💳 To pay by *card or another method*, open your wallet:\n".url('/wallet')
                ."\n\nYour balance updates automatically once payment is confirmed."
            );
        }

        $provider = self::MENU[$idx] ?? null;
        if (! $provider) {
            return FlowResult::retry('Please reply with a valid number, or type *cancel*.', 'choose_method');
        }

        $ctx->set('deposit_provider', $provider);

        return $this->askPhonePrompt($provider, $ctx);
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
        $phone = in_array(mb_strtolower(trim($input)), ['ok', 'yes'], true) && $default
            ? $default
            : $this->paynow->normalizeZwPhone($input);

        if ($phone === null) {
            return FlowResult::retry("That doesn't look like a valid number. Enter it as *0771234567*, or type *cancel*.", 'ask_phone');
        }

        $ctx->set('deposit_phone', $phone);

        return $this->confirmPrompt($ctx);
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
