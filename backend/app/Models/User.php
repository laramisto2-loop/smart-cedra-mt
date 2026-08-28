<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LogicException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function isPlatformAdministrator(): bool
    {
        return $this->is_platform_admin
            && $this->tenant_id === null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function createdContacts(): HasMany
    {
        return $this->hasMany(
            Contact::class,
            'created_by_user_id'
        );
    }

    public function recordedContactConsents(): HasMany
    {
        return $this->hasMany(
            ContactConsent::class,
            'recorded_by_user_id'
        );
    }

    public function recordedContactInteractions(): HasMany
    {
        return $this->hasMany(
            ContactInteraction::class,
            'recorded_by_user_id'
        );
    }

    public function createdCampaignTasks(): HasMany
    {
        return $this->hasMany(
            CampaignTask::class,
            'created_by_user_id'
        );
    }

    public function assignedCampaignTasks(): HasMany
    {
        return $this->hasMany(
            CampaignTask::class,
            'assigned_to_user_id'
        );
    }

    public function createdSegments(): HasMany
    {
        return $this->hasMany(
            Segment::class,
            'created_by_user_id'
        );
    }

    public function addedSegmentMemberships(): HasMany
    {
        return $this->hasMany(
            ContactSegment::class,
            'added_by_user_id'
        );
    }

    public function assignRole(Role $role): void
    {
        if (
            $this->tenant_id === null ||
            (int) $role->tenant_id !== (int) $this->tenant_id
        ) {
            throw new LogicException(
                'A user may only receive roles belonging to their own tenant.'
            );
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function removeRole(Role $role): void
    {
        if ((int) $role->tenant_id !== (int) $this->tenant_id) {
            throw new LogicException(
                'A user may only remove roles belonging to their own tenant.'
            );
        }

        $this->roles()->detach($role->id);
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()
            ->where('roles.slug', $roleSlug)
            ->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlug): void {
                $query->where('permissions.slug', $permissionSlug);
            })
            ->exists();
    }

    public function reportedIncidents(): HasMany
    {
        return $this->hasMany(
            Incident::class,
            'reported_by_user_id'
        );
    }

    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(
            Incident::class,
            'assigned_to_user_id'
        );
    }

    public function reviewedIncidents(): HasMany
    {
        return $this->hasMany(
            Incident::class,
            'reviewed_by_user_id'
        );
    }

    public function uploadedIncidentAttachments(): HasMany
    {
        return $this->hasMany(
            IncidentAttachment::class,
            'uploaded_by_user_id'
        );
    }

    public function reportedTurnoutSnapshots(): HasMany
    {
        return $this->hasMany(
            TurnoutSnapshot::class,
            'reported_by_user_id'
        );
    }

    public function createdMessageTemplates(): HasMany
    {
        return $this->hasMany(
            MessageTemplate::class,
            'created_by_user_id'
        );
    }

    public function sentOutboundMessages(): HasMany
    {
        return $this->hasMany(
            OutboundMessage::class,
            'sent_by_user_id'
        );
    }

    public function createdCallScripts(): HasMany
    {
        return $this->hasMany(
            CallScript::class,
            'created_by_user_id'
        );
    }

    public function createdCallQueues(): HasMany
    {
        return $this->hasMany(
            CallQueue::class,
            'created_by_user_id'
        );
    }

    public function callAssignments(): HasMany
    {
        return $this->hasMany(
            CallAssignment::class,
            'assigned_to_user_id'
        );
    }

    public function assignedCallAssignments(): HasMany
    {
        return $this->hasMany(
            CallAssignment::class,
            'assigned_by_user_id'
        );
    }

    public function performedCallAttempts(): HasMany
    {
        return $this->hasMany(
            CallAttempt::class,
            'performed_by_user_id'
        );
    }

    public function createdElectionContests(): HasMany
    {
        return $this->hasMany(
            ElectionContest::class,
            'created_by_user_id'
        );
    }

    public function activatedElectionContests(): HasMany
    {
        return $this->hasMany(
            ElectionContest::class,
            'activated_by_user_id'
        );
    }

    public function createdTallySheets(): HasMany
    {
        return $this->hasMany(
            TallySheet::class,
            'created_by_user_id'
        );
    }

    public function reviewedTallySheets(): HasMany
    {
        return $this->hasMany(
            TallySheet::class,
            'reviewed_by_user_id'
        );
    }

    public function approvedTallySheets(): HasMany
    {
        return $this->hasMany(
            TallySheet::class,
            'approved_by_user_id'
        );
    }

    public function enteredTallySubmissions(): HasMany
    {
        return $this->hasMany(
            TallySubmission::class,
            'entered_by_user_id'
        );
    }

    public function uploadedTallySheetAttachments(): HasMany
    {
        return $this->hasMany(
            TallySheetAttachment::class,
            'uploaded_by_user_id'
        );
    }
}
