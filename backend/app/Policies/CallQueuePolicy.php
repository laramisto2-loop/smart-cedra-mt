<?php

namespace App\Policies;

use App\Models\CallQueue;
use App\Models\User;

class CallQueuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('calls.queues.view');
    }

    public function view(
        User $user,
        CallQueue $callQueue
    ): bool {
        return $user->hasPermission('calls.queues.view')
            && $this->belongsToUsersTenant(
                $user,
                $callQueue
            );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('calls.queues.create');
    }

    public function update(
        User $user,
        CallQueue $callQueue
    ): bool {
        return $user->hasPermission('calls.queues.update')
            && $this->belongsToUsersTenant(
                $user,
                $callQueue
            )
            && ! in_array(
                $callQueue->status,
                ['completed', 'archived'],
                true
            );
    }

    public function assign(
        User $user,
        CallQueue $callQueue
    ): bool {
        return $user->hasPermission('calls.queues.assign')
            && $this->belongsToUsersTenant(
                $user,
                $callQueue
            )
            && $callQueue->status === 'active';
    }

    public function delete(
        User $user,
        CallQueue $callQueue
    ): bool {
        return $user->hasPermission('calls.queues.delete')
            && $this->belongsToUsersTenant(
                $user,
                $callQueue
            )
            && $callQueue->status === 'draft'
            && ! $callQueue->assignments()->exists();
    }

    public function restore(
        User $user,
        CallQueue $callQueue
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        CallQueue $callQueue
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        CallQueue $callQueue
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $callQueue->tenant_id;
    }
}
