<?php

namespace App\Policies;

use App\Models\CampaignTask;
use App\Models\User;

class CampaignTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('tasks.view');
    }

    public function view(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return $user->hasPermission('tasks.view')
            && $this->belongsToUsersTenant($user, $campaignTask)
            && $this->mayAccessTask($user, $campaignTask);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('tasks.create');
    }

    public function update(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return $user->hasPermission('tasks.update')
            && $this->belongsToUsersTenant($user, $campaignTask);
    }

    public function assign(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return $user->hasPermission('tasks.assign')
            && $this->belongsToUsersTenant($user, $campaignTask);
    }

    public function complete(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return $user->hasPermission('tasks.complete')
            && $this->belongsToUsersTenant($user, $campaignTask)
            && $this->mayAccessTask($user, $campaignTask);
    }

    public function delete(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return $user->hasPermission('tasks.delete')
            && $this->belongsToUsersTenant($user, $campaignTask);
    }

    public function restore(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $campaignTask->tenant_id;
    }

    private function mayAccessTask(
        User $user,
        CampaignTask $campaignTask
    ): bool {
        if ($user->hasPermission('tasks.update')) {
            return true;
        }

        return (int) $campaignTask->assigned_to_user_id === (int) $user->id;
    }
}
