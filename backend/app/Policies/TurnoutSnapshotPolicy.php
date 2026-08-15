<?php

namespace App\Policies;

use App\Models\TurnoutSnapshot;
use App\Models\User;

class TurnoutSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('turnout.view');
    }

    public function view(
        User $user,
        TurnoutSnapshot $turnoutSnapshot
    ): bool {
        return $user->hasPermission('turnout.view')
            && $this->belongsToUsersTenant(
                $user,
                $turnoutSnapshot
            )
            && $this->mayAccessSnapshot(
                $user,
                $turnoutSnapshot
            );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('turnout.create');
    }

    public function update(
        User $user,
        TurnoutSnapshot $turnoutSnapshot
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        TurnoutSnapshot $turnoutSnapshot
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        TurnoutSnapshot $turnoutSnapshot
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        TurnoutSnapshot $turnoutSnapshot
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        TurnoutSnapshot $turnoutSnapshot
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $turnoutSnapshot->tenant_id;
    }

    private function mayAccessSnapshot(
        User $user,
        TurnoutSnapshot $turnoutSnapshot
    ): bool {
        if (
            $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator')
        ) {
            return true;
        }

        return (int) $turnoutSnapshot->reported_by_user_id
            === (int) $user->id;
    }
}
