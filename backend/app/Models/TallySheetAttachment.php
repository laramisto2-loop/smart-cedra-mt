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

class TallySheetAttachment extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tally_sheet_id',
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
            TallySheetAttachment $attachment
        ): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $attachment->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A tally sheet attachment must belong to a tenant.'
                );
            }

            $attachment->tenant_id = (int) $tenantId;

            if ($attachment->tally_sheet_id === null) {
                throw new LogicException(
                    'A tally sheet attachment must belong to a tally sheet.'
                );
            }

            if (blank($attachment->client_uuid)) {
                $attachment->client_uuid = Str::uuid()->toString();
            }

            if (blank($attachment->disk)) {
                $attachment->disk = 'local';
            }

            if (
                $attachment->size_bytes === null
                || (int) $attachment->size_bytes < 1
            ) {
                throw new LogicException(
                    'A tally sheet attachment must contain a non-empty file.'
                );
            }

            if (
                blank($attachment->checksum_sha256)
                || preg_match(
                    '/^[a-f0-9]{64}$/i',
                    (string) $attachment->checksum_sha256
                ) !== 1
            ) {
                throw new LogicException(
                    'The tally sheet attachment checksum must be a valid SHA-256 value.'
                );
            }

            self::ensureRelationshipBelongsToTenant(
                TallySheet::class,
                $attachment->tally_sheet_id,
                (int) $tenantId,
                'tally sheet'
            );

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $attachment->uploaded_by_user_id,
                (int) $tenantId,
                'uploader'
            );

            self::ensureSheetIsNotFinalized(
                $attachment->tally_sheet_id
            );
        });

        static::updating(function (
            TallySheetAttachment $attachment
        ): void {
            if (
                $attachment->isDirty([
                    'tenant_id',
                    'tally_sheet_id',
                    'uploaded_by_user_id',
                    'client_uuid',
                    'disk',
                    'path',
                    'checksum_sha256',
                ])
            ) {
                throw new LogicException(
                    'The tally sheet attachment identity cannot be modified.'
                );
            }
        });

        static::deleting(function (
            TallySheetAttachment $attachment
        ): void {
            self::ensureSheetIsNotFinalized(
                $attachment->tally_sheet_id
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tallySheet(): BelongsTo
    {
        return $this->belongsTo(TallySheet::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id'
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function ensureRelationshipBelongsToTenant(
        string $modelClass,
        ?int $relatedId,
        int $tenantId,
        string $relationship
    ): void {
        if ($relatedId === null) {
            return;
        }

        $relatedTenantId = $modelClass::withoutGlobalScopes()
            ->whereKey($relatedId)
            ->value('tenant_id');

        if ((int) $relatedTenantId !== $tenantId) {
            throw new LogicException(
                "The tally sheet attachment {$relationship} must belong to the same tenant."
            );
        }
    }

    private static function ensureSheetIsNotFinalized(
        int $tallySheetId
    ): void {
        $status = TallySheet::withoutGlobalScopes()
            ->whereKey($tallySheetId)
            ->value('status');

        if (
            in_array(
                $status,
                [
                    TallySheet::STATUS_APPROVED,
                    TallySheet::STATUS_REJECTED,
                ],
                true
            )
        ) {
            throw new LogicException(
                'Attachments belonging to a finalized tally sheet cannot be changed or deleted.'
            );
        }
    }
}
