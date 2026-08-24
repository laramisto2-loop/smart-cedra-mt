<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\EnsuresParentBelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollingStation extends Model
{
    use Auditable, BelongsToTenant, EnsuresParentBelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'polling_center_id',
        'station_number',
        'name_en',
        'name_ar',
        'room',
        'registered_voters',
    ];

    protected function casts(): array
    {
        return [
            'registered_voters' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pollingCenter(): BelongsTo
    {
        return $this->belongsTo(PollingCenter::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    protected function tenantParentClass(): string
    {
        return PollingCenter::class;
    }

    protected function tenantParentForeignKey(): string
    {
        return 'polling_center_id';
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

// Polling Center → has many Polling Stations
// Polling Station → belongs to Polling Center
// Now protect the final level, PollingStation
