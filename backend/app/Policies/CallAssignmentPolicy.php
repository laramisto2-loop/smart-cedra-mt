<?php

namespace App\Policies;

use App\Models\CallAssignment;
use App\Models\User;

class CallAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'calls.assignments.view'
            );
    }

    public function view(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        return $user->hasPermission(
            'calls.assignments.view'
        )
            && $this->belongsToUsersTenant(
                $user,
                $callAssignment
            )
            && $this->mayAccessAssignment(
                $user,
                $callAssignment
            );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'calls.queues.assign'
            );
    }

    public function claim(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        return $user->hasPermission(
            'calls.assignments.claim'
        )
            && $this->belongsToUsersTenant(
                $user,
                $callAssignment
            )
            && $callAssignment->status === 'pending'
            && (
                $callAssignment->assigned_to_user_id === null
                || (int) $callAssignment->assigned_to_user_id
                    === (int) $user->id
            );
    }

    public function update(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        return $user->hasPermission(
            'calls.assignments.update'
        )
            && $this->belongsToUsersTenant(
                $user,
                $callAssignment
            )
            && $this->mayAccessAssignment(
                $user,
                $callAssignment
            )
            && ! in_array(
                $callAssignment->status,
                ['completed', 'skipped', 'cancelled'],
                true
            );
    }

    public function delete(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $callAssignment->tenant_id;
    }

    private function mayAccessAssignment(
        User $user,
        CallAssignment $callAssignment
    ): bool {
        if (
            $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator')
        ) {
            return true;
        }

        return (int) $callAssignment->assigned_to_user_id
            === (int) $user->id;
    }
}
