<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\EnsuresParentBelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use BelongsToTenant, EnsuresParentBelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'district_id',
        'name_en',
        'name_ar',
        'code',
        'type',
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

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function pollingCenters(): HasMany
    {
        return $this->hasMany(PollingCenter::class);
    }

    protected function tenantParentClass(): string
    {
        return District::class;
    }

    protected function tenantParentForeignKey(): string
    {
        return 'district_id';
    }
}

// District → has many Areas
// Area → belongs to District
// Area → has many Polling Centers
// This defines District as the required same-tenant parent.
