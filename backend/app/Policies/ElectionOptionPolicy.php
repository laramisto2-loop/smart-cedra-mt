<?php

namespace App\Policies;

use App\Models\ElectionContest;
use App\Models\ElectionOption;
use App\Models\User;

class ElectionOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.contests.view');
    }

    public function view(
        User $user,
        ElectionOption $electionOption
    ): bool {
        return $user->hasPermission('results.contests.view')
            && $this->belongsToUsersTenant($user, $electionOption);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.contests.update');
    }

    public function update(
        User $user,
        ElectionOption $electionOption
    ): bool {
        return $user->hasPermission('results.contests.update')
            && $this->belongsToUsersTenant($user, $electionOption)
            && $this->contestIsDraft($electionOption);
    }

    public function delete(
        User $user,
        ElectionOption $electionOption
    ): bool {
        return $user->hasPermission('results.contests.update')
            && $this->belongsToUsersTenant($user, $electionOption)
            && $this->contestIsDraft($electionOption)
            && ! $electionOption->tallyResults()->exists();
    }

    public function restore(
        User $user,
        ElectionOption $electionOption
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        ElectionOption $electionOption
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        ElectionOption $electionOption
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $electionOption->tenant_id;
    }

    private function contestIsDraft(
        ElectionOption $electionOption
    ): bool {
        return ElectionContest::withoutGlobalScopes()
            ->whereKey($electionOption->election_contest_id)
            ->where('tenant_id', $electionOption->tenant_id)
            ->where('status', ElectionContest::STATUS_DRAFT)
            ->exists();
    }
}
