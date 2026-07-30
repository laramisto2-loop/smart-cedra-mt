<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class TenantGeographyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('geography.view');
    }

    public function view(User $user, Model $geography): bool
    {
        return $user->hasPermission('geography.view')
            && $this->belongsToUsersTenant($user, $geography);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('geography.create');
    }

    public function update(User $user, Model $geography): bool
    {
        return $user->hasPermission('geography.update')
            && $this->belongsToUsersTenant($user, $geography);
    }

    public function delete(User $user, Model $geography): bool
    {
        return $user->hasPermission('geography.delete')
            && $this->belongsToUsersTenant($user, $geography);
    }

    public function restore(User $user, Model $geography): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $geography): bool
    {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        Model $geography
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $geography->getAttribute('tenant_id');
    }
}

// This base policy contains the rules once. Each model-specific policy will inherit them.
