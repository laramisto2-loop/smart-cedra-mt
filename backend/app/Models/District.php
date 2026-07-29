<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\EnsuresParentBelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use BelongsToTenant, EnsuresParentBelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'governorate_id',
        'name_en',
        'name_ar',
        'code',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    protected function tenantParentClass(): string
    {
        return Governorate::class;
    }

    protected function tenantParentForeignKey(): string
    {
        return 'governorate_id';
    }
}

// District’s parent model: Governorate
// District’s parent key: governorate_id
