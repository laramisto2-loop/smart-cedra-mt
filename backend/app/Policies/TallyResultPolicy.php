<?php

namespace App\Policies;

use App\Models\TallyResult;
use App\Models\TallySubmission;
use App\Models\User;

class TallyResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.tallies.view');
    }

    public function view(
        User $user,
        TallyResult $tallyResult
    ): bool {
        return $user->hasPermission('results.tallies.view')
            && $this->belongsToUsersTenant($user, $tallyResult)
            && $this->mayAccessResult($user, $tallyResult);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.tallies.update');
    }

    public function update(
        User $user,
        TallyResult $tallyResult
    ): bool {
        return $user->hasPermission('results.tallies.update')
            && $this->belongsToUsersTenant($user, $tallyResult)
            && $this->submissionIsOwnedDraft($user, $tallyResult);
    }

    public function delete(
        User $user,
        TallyResult $tallyResult
    ): bool {
        return $user->hasPermission('results.tallies.update')
            && $this->belongsToUsersTenant($user, $tallyResult)
            && $this->submissionIsOwnedDraft($user, $tallyResult);
    }

    public function restore(
        User $user,
        TallyResult $tallyResult
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        TallyResult $tallyResult
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        TallyResult $tallyResult
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $tallyResult->tenant_id;
    }

    private function mayAccessResult(
        User $user,
        TallyResult $tallyResult
    ): bool {
        if (
            $user->hasPermission('results.tallies.review')
            || $user->hasPermission('results.tallies.approve')
        ) {
            return true;
        }

        return TallySubmission::withoutGlobalScopes()
            ->whereKey($tallyResult->tally_submission_id)
            ->where('tenant_id', $user->tenant_id)
            ->where('entered_by_user_id', $user->id)
            ->exists();
    }

    private function submissionIsOwnedDraft(
        User $user,
        TallyResult $tallyResult
    ): bool {
        return TallySubmission::withoutGlobalScopes()
            ->whereKey($tallyResult->tally_submission_id)
            ->where('tenant_id', $user->tenant_id)
            ->where('entered_by_user_id', $user->id)
            ->where('status', TallySubmission::STATUS_DRAFT)
            ->exists();
    }
}
