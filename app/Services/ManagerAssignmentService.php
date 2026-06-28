<?php

namespace App\Services;

use App\Models\User;

class ManagerAssignmentService
{
    /**
     * Assign the least-loaded account and support managers if the user has reached
     * any platform monetization threshold and does not already have managers.
     */
    public function assignIfEligible(User $user): void
    {
        if (! MonetizationPlatformService::hasReachedAnyPlatformThreshold($user)) {
            return;
        }

        if (! $user->account_manager_id) {
            $manager = $this->leastLoadedManager('account_manager');
            if ($manager) {
                $user->account_manager_id = $manager->id;
            }
        }

        if (! $user->support_manager_id) {
            $manager = $this->leastLoadedManager('support_manager');
            if ($manager) {
                $user->support_manager_id = $manager->id;
            }
        }

        if ($user->isDirty(['account_manager_id', 'support_manager_id'])) {
            $user->save();
        }
    }

    private function leastLoadedManager(string $role): ?User
    {
        return User::where('manager_role', $role)
            ->where('is_active', true)
            ->withCount([
                'managedAccounts' => fn ($query) => $query->whereNotNull('account_manager_id'),
                'supportedAccounts' => fn ($query) => $query->whereNotNull('support_manager_id'),
            ])
            ->orderByRaw($role === 'account_manager' ? 'managed_accounts_count ASC' : 'supported_accounts_count ASC')
            ->first();
    }
}
