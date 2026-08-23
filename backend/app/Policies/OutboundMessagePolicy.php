<?php

namespace App\Policies;

use App\Models\OutboundMessage;
use App\Models\User;

class OutboundMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('messages.view');
    }

    public function view(
        User $user,
        OutboundMessage $outboundMessage
    ): bool {
        return $user->hasPermission('messages.view')
            && $this->belongsToUsersTenant(
                $user,
                $outboundMessage
            );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('messages.send');
    }

    public function update(
        User $user,
        OutboundMessage $outboundMessage
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        OutboundMessage $outboundMessage
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        OutboundMessage $outboundMessage
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        OutboundMessage $outboundMessage
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        OutboundMessage $outboundMessage
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $outboundMessage->tenant_id;
    }
}
