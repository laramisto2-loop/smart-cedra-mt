<?php

namespace App\Policies;

use App\Models\TallySheet;
use App\Models\User;

class TallySheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.tallies.view');
    }

    public function view(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return $user->hasPermission('results.tallies.view')
            && $this->belongsToUsersTenant($user, $tallySheet);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.tallies.create');
    }

    public function update(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return $user->hasPermission('results.tallies.update')
            && $this->belongsToUsersTenant($user, $tallySheet)
            && $this->mayManageSheet($user, $tallySheet)
            && in_array(
                $tallySheet->status,
                [
                    TallySheet::STATUS_PENDING,
                    TallySheet::STATUS_AWAITING_SECOND_ENTRY,
                ],
                true
            );
    }

    public function review(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return $user->hasPermission('results.tallies.review')
            && $this->belongsToUsersTenant($user, $tallySheet)
            && in_array(
                $tallySheet->status,
                [
                    TallySheet::STATUS_READY_FOR_REVIEW,
                    TallySheet::STATUS_DISCREPANCY,
                ],
                true
            );
    }

    public function approve(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return $user->hasPermission('results.tallies.approve')
            && $this->belongsToUsersTenant($user, $tallySheet)
            && $tallySheet->status
                === TallySheet::STATUS_READY_FOR_REVIEW;
    }

    public function reject(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return $user->hasPermission('results.tallies.approve')
            && $this->belongsToUsersTenant($user, $tallySheet)
            && in_array(
                $tallySheet->status,
                [
                    TallySheet::STATUS_READY_FOR_REVIEW,
                    TallySheet::STATUS_DISCREPANCY,
                ],
                true
            );
    }

    public function delete(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        TallySheet $tallySheet
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $tallySheet->tenant_id;
    }

    private function mayManageSheet(
        User $user,
        TallySheet $tallySheet
    ): bool {
        if (
            $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator')
        ) {
            return true;
        }

        return (int) $tallySheet->created_by_user_id
            === (int) $user->id;
    }
}
