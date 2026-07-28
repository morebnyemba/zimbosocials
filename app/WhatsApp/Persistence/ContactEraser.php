<?php

namespace App\WhatsApp\Persistence;

use App\Models\AdvertBooking;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSavedOrder;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Erases a WhatsApp contact — the identity, the transcript, the flow state and
 * the archived media — in one place, so nothing is left orphaned on disk or in
 * a table someone forgot about.
 *
 * Financial history is deliberately NOT collateral damage: orders, transactions
 * and the wallet belong to the User record and are kept unless the caller
 * explicitly asks to remove the user AND that user has nothing outstanding.
 */
class ContactEraser
{
    /**
     * Delete the WhatsApp identity and everything written about the chat.
     *
     * @param  bool  $alsoUser  Also delete the linked app user. Refused when they
     *                          have orders, transactions or a balance — that's
     *                          financial history, not chat data.
     * @return array{ok:bool, reason?:string, deleted?:array<string,int>}
     */
    public function erase(WhatsAppAccount $account, bool $alsoUser = false, ?int $actorId = null): array
    {
        $phone = (string) $account->wa_phone;
        if ($phone === '') {
            return ['ok' => false, 'reason' => 'no_phone'];
        }

        $user = $account->user;

        if ($alsoUser && $user && $this->hasFinancialHistory($user)) {
            return ['ok' => false, 'reason' => 'has_financial_history'];
        }

        $deleted = [];

        DB::transaction(function () use ($phone, $account, $user, $alsoUser, &$deleted): void {
            $deleted['messages'] = WhatsAppMessage::where('wa_phone', $phone)->delete();
            $deleted['sessions'] = WhatsAppSession::where('wa_phone', $phone)->delete();
            $deleted['saved_orders'] = WhatsAppSavedOrder::where('wa_phone', $phone)->delete();
            // An advert booking is a paid commitment — detach it from the phone
            // rather than deleting the record and losing what was sold.
            $deleted['adverts_detached'] = AdvertBooking::where('wa_phone', $phone)->update(['wa_phone' => null]);

            $account->delete();
            $deleted['accounts'] = 1;

            if ($alsoUser && $user) {
                $user->delete();
                $deleted['users'] = 1;
            }
        });

        // Outside the transaction: disk work can't be rolled back, and a failed
        // unlink must never undo the database deletion.
        $deleted['media_files'] = $this->purgeMedia($phone);

        AuditLog::log(
            'whatsapp.contact_erased',
            $actorId,
            WhatsAppAccount::class,
            (int) $account->getKey(),
            ['wa_phone' => $this->mask($phone)],
            ['also_user' => $alsoUser, 'deleted' => $deleted],
        );

        return ['ok' => true, 'deleted' => $deleted];
    }

    /** Anything that means we must keep the user record for our own books. */
    public function hasFinancialHistory(User $user): bool
    {
        return (float) $user->balance > 0
            || $user->orders()->exists()
            || $user->transactions()->exists();
    }

    /** Remove archived photos/voice notes for this number. */
    private function purgeMedia(string $phone): int
    {
        $dir = 'wa-media/'.preg_replace('/\D+/', '', $phone);

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($dir)) {
                return 0;
            }
            $count = count($disk->files($dir));
            $disk->deleteDirectory($dir);

            return $count;
        } catch (\Throwable $e) {
            Log::warning('Could not purge WhatsApp media', ['dir' => $dir, 'message' => $e->getMessage()]);

            return 0;
        }
    }

    /** Never write a full number into the audit trail. */
    private function mask(string $phone): string
    {
        return strlen($phone) > 4 ? str_repeat('*', strlen($phone) - 4).substr($phone, -4) : $phone;
    }
}
