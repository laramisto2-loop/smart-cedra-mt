<?php

namespace App\Policies;

use App\Models\MessageTemplate;
use App\Models\User;

class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'messages.templates.view'
            );
    }

    public function view(
        User $user,
        MessageTemplate $messageTemplate
    ): bool {
        return $user->hasPermission(
            'messages.templates.view'
        ) && $this->belongsToUsersTenant(
            $user,
            $messageTemplate
        );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'messages.templates.create'
            );
    }

    public function update(
        User $user,
        MessageTemplate $messageTemplate
    ): bool {
        return $user->hasPermission(
            'messages.templates.update'
        )
            && $this->belongsToUsersTenant(
                $user,
                $messageTemplate
            )
            && $messageTemplate->status !== 'approved';
    }

    public function approve(
        User $user,
        MessageTemplate $messageTemplate
    ): bool {
        return $user->hasPermission(
            'messages.templates.approve'
        ) && $this->belongsToUsersTenant(
            $user,
            $messageTemplate
        );
    }

    public function delete(
        User $user,
        MessageTemplate $messageTemplate
    ): bool {
        return $user->hasPermission(
            'messages.templates.delete'
        )
            && $this->belongsToUsersTenant(
                $user,
                $messageTemplate
            )
            && ! $messageTemplate
                ->outboundMessages()
                ->exists();
    }

    public function restore(
        User $user,
        MessageTemplate $messageTemplate
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        MessageTemplate $messageTemplate
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        MessageTemplate $messageTemplate
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $messageTemplate->tenant_id;
    }
}
