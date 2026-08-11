<?php

namespace App\Policies;

use App\Models\Segment;
use App\Models\User;

class SegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('segments.view');
    }

    public function view(User $user, Segment $segment): bool
    {
        return $user->hasPermission('segments.view')
            && $this->belongsToUsersTenant($user, $segment);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('segments.create');
    }

    public function update(User $user, Segment $segment): bool
    {
        return $user->hasPermission('segments.update')
            && $this->belongsToUsersTenant($user, $segment);
    }

    public function delete(User $user, Segment $segment): bool
    {
        return $user->hasPermission('segments.delete')
            && $this->belongsToUsersTenant($user, $segment);
    }

    public function manageMembers(
        User $user,
        Segment $segment
    ): bool {
        return $segment->type === 'static'
            && $user->hasPermission('segments.members.manage')
            && $this->belongsToUsersTenant($user, $segment);
    }

    public function restore(User $user, Segment $segment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Segment $segment): bool
    {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        Segment $segment
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $segment->tenant_id;
    }
}
