<?php

namespace App\WhatsApp\Deposit;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use App\Services\NotificationService;
use App\WhatsApp\Messaging\WhatsAppGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attach an inbound WhatsApp image/PDF as proof of payment for a pending
 * manual deposit — the in-chat equivalent of the wallet page's proof upload.
 *
 * A manual deposit (bank/wallet transfer) sits 'pending' awaiting proof until
 * an admin verifies it and credits the balance (plus the manual bonus). This
 * downloads the media, stores it on the same public disk / path the web uses,
 * stamps proof_url + notes on the newest matching deposit, and audits it.
 */
class ProofIntake
{
    /** Same allow-list the wallet page enforces, plus PDF receipts. */
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB, matching web's max:5120

    /** Gateway methods auto-confirm via poll/webhook and never take proof. */
    private const UNAMBIGUOUS_GATEWAY_METHODS = ['paynow', 'ecocash', 'onemoney'];

    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly DepositService $deposits,
    ) {}

    /**
     * @param  array  $media  Normalized media node: ['id','mime','kind','filename'?]
     * @return array{ok:bool, transaction?:Transaction, reason?:string}
     */
    public function intake(User $user, array $media): array
    {
        $transaction = $this->pendingManualDeposit($user);
        if (! $transaction) {
            return ['ok' => false, 'reason' => 'no_pending'];
        }

        $mime = strtolower((string) ($media['mime'] ?? ''));
        // Strip any "; codecs=" suffix Meta may append.
        $mime = trim(explode(';', $mime)[0]);
        $ext = self::MIME_EXT[$mime] ?? null;
        if ($ext === null) {
            return ['ok' => false, 'reason' => 'bad_type', 'transaction' => $transaction];
        }

        $mediaId = (string) ($media['id'] ?? '');
        if ($mediaId === '') {
            return ['ok' => false, 'reason' => 'no_media_id', 'transaction' => $transaction];
        }

        $dl = $this->gateway->downloadMedia($mediaId);
        if (empty($dl['ok'])) {
            return ['ok' => false, 'reason' => 'download_failed', 'transaction' => $transaction];
        }

        $contents = (string) $dl['contents'];
        if (strlen($contents) > self::MAX_BYTES) {
            return ['ok' => false, 'reason' => 'too_large', 'transaction' => $transaction];
        }

        $path = 'proofs/'.$user->getKey().'/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('public')->put($path, $contents);

        $oldProofUrl = $transaction->proof_url;
        $transaction->update([
            'proof_url' => '/storage/'.$path,
            'notes' => 'Proof of payment submitted via WhatsApp. Awaiting admin approval.',
        ]);

        AuditLog::dispatchLog(
            action: 'transaction.deposit_proof_submitted',
            userId: (int) $user->getKey(),
            modelType: Transaction::class,
            modelId: (int) $transaction->getKey(),
            oldValues: ['proof_url' => $oldProofUrl],
            newValues: ['proof_url' => (string) $transaction->getAttribute('proof_url'), 'source' => 'whatsapp'],
        );

        $this->notifyAdmins($user, $transaction);

        return ['ok' => true, 'transaction' => $transaction];
    }

    /**
     * Alert admins that a manual deposit is ready to review — otherwise a
     * WhatsApp proof just sits in the queue until someone happens to look.
     * Best-effort: the proof is already saved, so a notify failure never fails
     * the intake.
     */
    private function notifyAdmins(User $user, Transaction $transaction): void
    {
        try {
            $cur = $user->currency ?? 'USD';
            $amount = number_format((float) abs($transaction->amount), 2);
            $method = $transaction->method ? ucfirst((string) $transaction->method) : 'manual';

            NotificationService::notifyAdmins(
                'admin_deposit_proof',
                'Deposit proof submitted (WhatsApp)',
                "{$user->name} submitted proof for a {$amount} {$cur} {$method} deposit (#{$transaction->getKey()}) via WhatsApp — verify and credit it in Transactions.",
                [
                    'transaction_id' => (int) $transaction->getKey(),
                    'user_name' => $user->name,
                    'amount' => $amount,
                    'method' => $transaction->method,
                    'source' => 'whatsapp',
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp proof admin-notify failed', [
                'transaction_id' => $transaction->getKey(),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The newest pending manual deposit still awaiting proof. Manual = a
     * non-gateway method (a Paynow express deposit auto-confirms and must never
     * accept proof).
     */
    private function pendingManualDeposit(User $user): ?Transaction
    {
        return Transaction::where('user_id', $user->getKey())
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->whereNull('proof_url')
            ->latest()
            ->get()
            ->first(fn (Transaction $t) => $this->isManualDeposit($t));
    }

    private function isManualDeposit(Transaction $t): bool
    {
        $method = (string) $t->method;

        if (in_array($method, self::UNAMBIGUOUS_GATEWAY_METHODS, true)) {
            return false;
        }
        // A Paynow poll-URL reference marks an express (gateway) deposit.
        if (is_string($t->reference) && str_starts_with($t->reference, 'http')) {
            return false;
        }

        return $this->deposits->isManualMethod($method);
    }
}
