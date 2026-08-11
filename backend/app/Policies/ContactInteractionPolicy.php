<?php

namespace App\Policies;

use App\Models\ContactInteraction;
use App\Models\User;

class ContactInteractionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('interactions.view');
    }

    public function view(
        User $user,
        ContactInteraction $interaction
    ): bool {
        return $user->hasPermission('interactions.view')
            && $this->belongsToUsersTenant($user, $interaction);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('interactions.create');
    }

    public function update(
        User $user,
        ContactInteraction $interaction
    ): bool {
        return $user->hasPermission('interactions.update')
            && $this->belongsToUsersTenant($user, $interaction);
    }

    public function delete(
        User $user,
        ContactInteraction $interaction
    ): bool {
        return $user->hasPermission('interactions.delete')
            && $this->belongsToUsersTenant($user, $interaction);
    }

    public function restore(
        User $user,
        ContactInteraction $interaction
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        ContactInteraction $interaction
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        ContactInteraction $interaction
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $interaction->tenant_id;
    }
}
