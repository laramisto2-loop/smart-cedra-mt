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

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }
}

// This lets us retrieve geography directly from a tenant:
// $tenant->governorates;
// $tenant->districts;
// $tenant->pollingCenters;
