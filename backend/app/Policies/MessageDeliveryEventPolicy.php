<?php

namespace App\Policies;

use App\Models\MessageDeliveryEvent;
use App\Models\User;

class MessageDeliveryEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'messages.delivery.view'
            );
    }

    public function view(
        User $user,
        MessageDeliveryEvent $messageDeliveryEvent
    ): bool {
        return $user->hasPermission(
            'messages.delivery.view'
        ) && $this->belongsToUsersTenant(
            $user,
            $messageDeliveryEvent
        );
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(
        User $user,
        MessageDeliveryEvent $messageDeliveryEvent
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        MessageDeliveryEvent $messageDeliveryEvent
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        MessageDeliveryEvent $messageDeliveryEvent
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        MessageDeliveryEvent $messageDeliveryEvent
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        MessageDeliveryEvent $messageDeliveryEvent
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $messageDeliveryEvent->tenant_id;
    }
}

// Only authorized users can read delivery events. Creation will later be restricted to the trusted provider-processing service, while editing and deletion remain forbidden
