<?php

namespace App\Policies;

use App\Models\ElectionContest;
use App\Models\User;

class ElectionContestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.contests.view');
    }

    public function view(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return $user->hasPermission('results.contests.view')
            && $this->belongsToUsersTenant($user, $electionContest);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.contests.create');
    }

    public function update(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return $user->hasPermission('results.contests.update')
            && $this->belongsToUsersTenant($user, $electionContest)
            && $electionContest->isDraft();
    }

    public function activate(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return $user->hasPermission('results.contests.activate')
            && $this->belongsToUsersTenant($user, $electionContest)
            && $electionContest->isDraft();
    }

    public function close(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return $user->hasPermission('results.contests.activate')
            && $this->belongsToUsersTenant($user, $electionContest)
            && $electionContest->isActive();
    }

    public function delete(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return $user->hasPermission('results.contests.delete')
            && $this->belongsToUsersTenant($user, $electionContest)
            && $electionContest->isDraft()
            && ! $electionContest->tallySheets()->exists();
    }

    public function restore(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        ElectionContest $electionContest
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $electionContest->tenant_id;
    }
}
