<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class IncidentAttachment extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'incident_id',
        'uploaded_by_user_id',
        'client_uuid',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'captured_at',
        'client_updated_at',
    ];

    protected $hidden = [
        'path',
        'checksum_sha256',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'captured_at' => 'datetime',
            'client_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (
            IncidentAttachment $attachment
        ): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $attachment->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'An incident attachment must belong to a tenant.'
                );
            }

            if (blank($attachment->client_uuid)) {
                $attachment->client_uuid = Str::uuid()->toString();
            }

            self::ensureIncidentBelongsToTenant(
                $attachment->incident_id,
                (int) $tenantId
            );

            self::ensureUploaderBelongsToTenant(
                $attachment->uploaded_by_user_id,
                (int) $tenantId
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id'
        );
    }

    private static function ensureIncidentBelongsToTenant(
        int $incidentId,
        int $tenantId
    ): void {
        $incidentTenantId = Incident::withoutGlobalScopes()
            ->whereKey($incidentId)
            ->value('tenant_id');

        if ((int) $incidentTenantId !== $tenantId) {
            throw new LogicException(
                'The attachment incident must belong to the same tenant.'
            );
        }
    }

    private static function ensureUploaderBelongsToTenant(
        ?int $userId,
        int $tenantId
    ): void {
        if ($userId === null) {
            return;
        }

        $userTenantId = User::query()
            ->whereKey($userId)
            ->value('tenant_id');

        if ((int) $userTenantId !== $tenantId) {
            throw new LogicException(
                'The attachment uploader must belong to the same tenant.'
            );
        }
    }
}
