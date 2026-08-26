<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\EnsuresParentBelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollingCenter extends Model
{
    use Auditable, BelongsToTenant, EnsuresParentBelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'area_id',
        'name_en',
        'name_ar',
        'code',
        'address_en',
        'address_ar',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function pollingStations(): HasMany
    {
        return $this->hasMany(PollingStation::class);
    }

    protected function tenantParentClass(): string
    {
        return Area::class;
    }

    protected function tenantParentForeignKey(): string
    {
        return 'area_id';
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function turnoutSnapshots(): HasMany
    {
        return $this->hasMany(TurnoutSnapshot::class);
    }

    public function tallySheets(): HasMany
    {
        return $this->hasMany(TallySheet::class);
    }
}

// Area → has many Polling Centers
// Polling Center → belongs to Area
// Polling Center → has many Polling Stations
// Now apply the same protection to PollingCenter
