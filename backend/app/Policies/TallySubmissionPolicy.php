<?php

namespace App\Policies;

use App\Models\TallySheet;
use App\Models\TallySubmission;
use App\Models\User;

class TallySubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.tallies.view');
    }

    public function view(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return $user->hasPermission('results.tallies.view')
            && $this->belongsToUsersTenant($user, $tallySubmission)
            && $this->mayAccessSubmission($user, $tallySubmission);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.tallies.submit');
    }

    public function update(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return $user->hasPermission('results.tallies.update')
            && $this->belongsToUsersTenant($user, $tallySubmission)
            && $this->belongsToUser($user, $tallySubmission)
            && $tallySubmission->isDraft()
            && $this->sheetAcceptsEntries($tallySubmission);
    }

    public function submit(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return $user->hasPermission('results.tallies.submit')
            && $this->belongsToUsersTenant($user, $tallySubmission)
            && $this->belongsToUser($user, $tallySubmission)
            && $tallySubmission->isDraft()
            && $this->sheetAcceptsEntries($tallySubmission);
    }

    public function delete(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return $user->hasPermission('results.tallies.update')
            && $this->belongsToUsersTenant($user, $tallySubmission)
            && $this->belongsToUser($user, $tallySubmission)
            && $tallySubmission->isDraft()
            && $this->sheetAcceptsEntries($tallySubmission);
    }

    public function restore(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $tallySubmission->tenant_id;
    }

    private function belongsToUser(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        return (int) $tallySubmission->entered_by_user_id
            === (int) $user->id;
    }

    private function mayAccessSubmission(
        User $user,
        TallySubmission $tallySubmission
    ): bool {
        if (
            $user->hasPermission('results.tallies.review')
            || $user->hasPermission('results.tallies.approve')
        ) {
            return true;
        }

        return $this->belongsToUser($user, $tallySubmission);
    }

    private function sheetAcceptsEntries(
        TallySubmission $tallySubmission
    ): bool {
        return TallySheet::withoutGlobalScopes()
            ->whereKey($tallySubmission->tally_sheet_id)
            ->where('tenant_id', $tallySubmission->tenant_id)
            ->whereIn(
                'status',
                [
                    TallySheet::STATUS_PENDING,
                    TallySheet::STATUS_AWAITING_SECOND_ENTRY,
                ]
            )
            ->exists();
    }
}
