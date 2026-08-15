<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('incidents.view');
    }

    public function view(
        User $user,
        Incident $incident
    ): bool {
        return $user->hasPermission('incidents.view')
            && $this->belongsToUsersTenant($user, $incident)
            && $this->mayAccessIncident($user, $incident);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('incidents.create');
    }

    public function update(
        User $user,
        Incident $incident
    ): bool {
        if (
            ! $user->hasPermission('incidents.update')
            || ! $this->belongsToUsersTenant($user, $incident)
        ) {
            return false;
        }

        if ($user->hasPermission('incidents.review')) {
            return true;
        }

        return $incident->status === 'submitted'
            && (int) $incident->reported_by_user_id === (int) $user->id;
    }

    public function assign(
        User $user,
        Incident $incident
    ): bool {
        return $user->hasPermission('incidents.assign')
            && $this->belongsToUsersTenant($user, $incident);
    }

    public function review(
        User $user,
        Incident $incident
    ): bool {
        return $user->hasPermission('incidents.review')
            && $this->belongsToUsersTenant($user, $incident);
    }

    public function manageAttachments(
        User $user,
        Incident $incident
    ): bool {
        if (
            ! $user->hasPermission('incidents.attachments.manage')
            || ! $this->belongsToUsersTenant($user, $incident)
        ) {
            return false;
        }

        if ($user->hasPermission('incidents.review')) {
            return true;
        }

        return $incident->status === 'submitted'
            && (int) $incident->reported_by_user_id === (int) $user->id;
    }

    public function delete(
        User $user,
        Incident $incident
    ): bool {
        return $user->hasPermission('incidents.delete')
            && $this->belongsToUsersTenant($user, $incident);
    }

    public function restore(
        User $user,
        Incident $incident
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Incident $incident
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        Incident $incident
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $incident->tenant_id;
    }

    private function mayAccessIncident(
        User $user,
        Incident $incident
    ): bool {
        if ($user->hasPermission('incidents.review')) {
            return true;
        }

        return (int) $incident->reported_by_user_id === (int) $user->id
            || (int) $incident->assigned_to_user_id === (int) $user->id;
    }
}
