<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function view(User $user, User $targetUser): bool
    {
        return $user->hasPermission('users.manage')
            && $this->belongsToUsersTenant($user, $targetUser);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('users.manage');
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->hasPermission('users.manage')
            && $this->belongsToUsersTenant($user, $targetUser);
    }

    public function delete(User $user, User $targetUser): bool
    {
        return $user->hasPermission('users.manage')
            && $this->belongsToUsersTenant($user, $targetUser)
            && $user->isNot($targetUser);
    }

    public function restore(User $user, User $targetUser): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $targetUser): bool
    {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        User $targetUser
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $targetUser->tenant_id;
    }
}
