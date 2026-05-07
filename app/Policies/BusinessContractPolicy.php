<?php

namespace App\Policies;

use App\Models\BusinessContract;
use App\Models\User;

class BusinessContractPolicy
{
    /**
     * Only the contract owner (or admin) may view the contract detail page.
     */
    public function view(User $user, BusinessContract $contract): bool
    {
        return $user->id === (int) $contract->user_id || $user->isAdmin();
    }

    /**
     * Only business accounts can create contracts.
     */
    public function create(User $user): bool
    {
        return $user->account_type === 'business';
    }

    /**
     * Only the contract owner (or admin) may update / manage applications.
     */
    public function update(User $user, BusinessContract $contract): bool
    {
        return $user->id === (int) $contract->user_id || $user->isAdmin();
    }

    /**
     * Only the contract owner (or admin) may close the contract.
     */
    public function close(User $user, BusinessContract $contract): bool
    {
        return $user->id === (int) $contract->user_id || $user->isAdmin();
    }

    /**
     * Approved marketers / resellers can apply (not the contract owner).
     */
    public function apply(User $user, BusinessContract $contract): bool
    {
        if ($user->id === (int) $contract->user_id) {
            return false;
        }

        if (!in_array($user->role, ['marketer', 'reseller'], true)) {
            return false;
        }

        if ($user->marketer_status !== 'approved') {
            return false;
        }

        return $contract->status === 'open';
    }
}
