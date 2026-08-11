<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.view')
            && $this->belongsToUsersTenant($user, $contact);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.update')
            && $this->belongsToUsersTenant($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.delete')
            && $this->belongsToUsersTenant($user, $contact);
    }

    public function manageConsent(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.consent.manage')
            && $this->belongsToUsersTenant($user, $contact);
    }

    public function import(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('contacts.import');
    }

    public function export(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('contacts.export');
    }

    public function restore(User $user, Contact $contact): bool
    {
        return false;
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        return false;
    }

    private function belongsToUsersTenant(
        User $user,
        Contact $contact
    ): bool {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $contact->tenant_id;
    }
}
