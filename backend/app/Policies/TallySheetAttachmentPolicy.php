<?php

namespace App\Policies;

use App\Models\TallySheet;
use App\Models\TallySheetAttachment;
use App\Models\User;

class TallySheetAttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('results.tallies.view');
    }

    public function view(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return $user->hasPermission('results.tallies.view')
            && $this->belongsToUsersTenant(
                $user,
                $tallySheetAttachment
            )
            && $this->mayAccessAttachment(
                $user,
                $tallySheetAttachment
            );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission(
                'results.attachments.manage'
            );
    }

    public function update(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return $user->hasPermission(
            'results.attachments.manage'
        )
            && $this->belongsToUsersTenant(
                $user,
                $tallySheetAttachment
            )
            && $this->mayDeleteAttachment(
                $user,
                $tallySheetAttachment
            )
            && $this->sheetIsNotFinalized(
                $tallySheetAttachment
            );
    }

    public function restore(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id
                === (int) $tallySheetAttachment->tenant_id;
    }

    private function mayAccessAttachment(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        if (
            $user->hasPermission('results.tallies.review')
            || $user->hasPermission('results.tallies.approve')
        ) {
            return true;
        }

        if (
            (int) $tallySheetAttachment->uploaded_by_user_id
            === (int) $user->id
        ) {
            return true;
        }

        return $this->sheetWasCreatedByUser(
            $user,
            $tallySheetAttachment
        );
    }

    private function mayDeleteAttachment(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        if (
            $user->hasPermission('results.tallies.review')
            || $user->hasPermission('results.tallies.approve')
        ) {
            return true;
        }

        return (int) $tallySheetAttachment->uploaded_by_user_id
            === (int) $user->id;
    }

    private function sheetWasCreatedByUser(
        User $user,
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return TallySheet::withoutGlobalScopes()
            ->whereKey($tallySheetAttachment->tally_sheet_id)
            ->where('tenant_id', $user->tenant_id)
            ->where('created_by_user_id', $user->id)
            ->exists();
    }

    private function sheetIsNotFinalized(
        TallySheetAttachment $tallySheetAttachment
    ): bool {
        return TallySheet::withoutGlobalScopes()
            ->whereKey($tallySheetAttachment->tally_sheet_id)
            ->where('tenant_id', $tallySheetAttachment->tenant_id)
            ->whereNotIn(
                'status',
                [
                    TallySheet::STATUS_APPROVED,
                    TallySheet::STATUS_REJECTED,
                ]
            )
            ->exists();
    }
}
