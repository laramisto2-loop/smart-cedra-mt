<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function pollingCenters(): HasMany
    {
        return $this->hasMany(PollingCenter::class);
    }

    public function pollingStations(): HasMany
    {
        return $this->hasMany(PollingStation::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function contactConsents(): HasMany
    {
        return $this->hasMany(ContactConsent::class);
    }

    public function contactInteractions(): HasMany
    {
        return $this->hasMany(ContactInteraction::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    public function contactSegmentMemberships(): HasMany
    {
        return $this->hasMany(ContactSegment::class);
    }

    public function campaignTasks(): HasMany
    {
        return $this->hasMany(CampaignTask::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function incidentAttachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class);
    }

    public function turnoutSnapshots(): HasMany
    {
        return $this->hasMany(TurnoutSnapshot::class);
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }

    public function messageDeliveryEvents(): HasMany
    {
        return $this->hasMany(MessageDeliveryEvent::class);
    }

    public function callScripts(): HasMany
    {
        return $this->hasMany(CallScript::class);
    }

    public function callQueues(): HasMany
    {
        return $this->hasMany(CallQueue::class);
    }

    public function callAssignments(): HasMany
    {
        return $this->hasMany(CallAssignment::class);
    }

    public function callAttempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class);
    }
}

// This lets us retrieve geography directly from a tenant:
// $tenant->governorates;
// $tenant->districts;
// $tenant->pollingCenters;
