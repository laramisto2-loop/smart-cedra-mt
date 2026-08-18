<?php

namespace App\Policies;

use App\Models\CallAttempt;
use App\Models\User;

class CallAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'calls.attempts.view'
            );
    }

    public function view(
        User $user,
        CallAttempt $callAttempt
    ): bool {
        return $user->hasPermission(
            'calls.attempts.view'
        )
            && $this->belongsToUsersTenant(
                $user,
                $callAttempt
            )
            && $this->mayAccessAttempt(
                $user,
                $callAttempt
            );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'calls.attempts.create'
            );
    }

    public function update(
        User $user,
        CallAttempt $callAttempt
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        CallAttempt $callAttempt
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        CallAttempt $callAttempt
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        CallAttempt $callAttempt
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        CallAttempt $callAttempt
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $callAttempt->tenant_id;
    }

    private function mayAccessAttempt(
        User $user,
        CallAttempt $callAttempt
    ): bool {
        if (
            $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator')
        ) {
            return true;
        }

        if (
            (int) $callAttempt->performed_by_user_id
            === (int) $user->id
        ) {
            return true;
        }

        return $callAttempt->callAssignment()
            ->where(
                'assigned_to_user_id',
                $user->id
            )
            ->exists();
    }
}
