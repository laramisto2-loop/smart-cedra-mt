<?php

namespace App\Policies;

use App\Models\CallScript;
use App\Models\User;

class CallScriptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('calls.scripts.view');
    }

    public function view(
        User $user,
        CallScript $callScript
    ): bool {
        return $user->hasPermission('calls.scripts.view')
            && $this->belongsToUsersTenant(
                $user,
                $callScript
            );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('calls.scripts.create');
    }

    public function update(
        User $user,
        CallScript $callScript
    ): bool {
        return $user->hasPermission('calls.scripts.update')
            && $this->belongsToUsersTenant(
                $user,
                $callScript
            )
            && $callScript->status !== 'archived';
    }

    public function activate(
        User $user,
        CallScript $callScript
    ): bool {
        return $user->hasPermission('calls.scripts.activate')
            && $this->belongsToUsersTenant(
                $user,
                $callScript
            )
            && $callScript->status !== 'archived';
    }

    public function delete(
        User $user,
        CallScript $callScript
    ): bool {
        return $user->hasPermission('calls.scripts.delete')
            && $this->belongsToUsersTenant(
                $user,
                $callScript
            )
            && $callScript->status === 'draft'
            && ! $callScript->queues()->exists();
    }

    public function restore(
        User $user,
        CallScript $callScript
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        CallScript $callScript
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        CallScript $callScript
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $callScript->tenant_id;
    }
}
