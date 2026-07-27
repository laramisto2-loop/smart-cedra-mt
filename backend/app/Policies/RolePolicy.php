<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage')
            && $this->belongsToUsersTenant($user, $role);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage')
            && $this->belongsToUsersTenant($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage')
            && $this->belongsToUsersTenant($user, $role);
    }

    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }

    private function belongsToUsersTenant(User $user, Role $role): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $role->tenant_id;
    }
}
