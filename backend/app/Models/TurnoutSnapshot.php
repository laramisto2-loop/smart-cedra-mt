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

class TurnoutSnapshot extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const SOURCES = [
        'field',
        'admin',
    ];

    protected $fillable = [
        'tenant_id',
        'reported_by_user_id',
        'polling_center_id',
        'polling_station_id',
        'client_uuid',
        'reference_code',
        'registered_voters',
        'turnout_count',
        'source',
        'notes',
        'captured_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_voters' => 'integer',
            'turnout_count' => 'integer',
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TurnoutSnapshot $snapshot): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $snapshot->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A turnout snapshot must belong to a tenant.'
                );
            }

            if ($snapshot->polling_center_id === null) {
                throw new LogicException(
                    'A turnout snapshot must belong to a polling center.'
                );
            }

            if (blank($snapshot->client_uuid)) {
                $snapshot->client_uuid = Str::uuid()->toString();
            }

            if (blank($snapshot->reference_code)) {
                $compactUuid = str_replace(
                    '-',
                    '',
                    (string) $snapshot->client_uuid
                );

                $snapshot->reference_code = 'TUR-'.strtoupper(
                    substr($compactUuid, 0, 12)
                );
            }

            if (blank($snapshot->source)) {
                $snapshot->source = 'field';
            }

            if (! in_array($snapshot->source, self::SOURCES, true)) {
                throw new LogicException(
                    'The turnout snapshot source is invalid.'
                );
            }

            self::ensureRelationshipBelongsToTenant(
                User::class,
                $snapshot->reported_by_user_id,
                (int) $tenantId,
                'reporter'
            );

            self::ensureRelationshipBelongsToTenant(
                PollingCenter::class,
                $snapshot->polling_center_id,
                (int) $tenantId,
                'polling center'
            );

            self::ensureRelationshipBelongsToTenant(
                PollingStation::class,
                $snapshot->polling_station_id,
                (int) $tenantId,
                'polling station'
            );

            self::ensureGeographyIsConsistent($snapshot);
            self::ensureCountsAreValid($snapshot);
        });

        static::updating(function (): void {
            throw new LogicException(
                'Turnout snapshots are immutable. Record a new snapshot to correct a count.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Turnout snapshots cannot be deleted because they form part of the reporting history.'
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by_user_id'
        );
    }

    public function pollingCenter(): BelongsTo
    {
        return $this->belongsTo(PollingCenter::class);
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
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
                "The turnout snapshot {$relationship} must belong to the same tenant."
            );
        }
    }

    private static function ensureGeographyIsConsistent(
        TurnoutSnapshot $snapshot
    ): void {
        if ($snapshot->polling_station_id === null) {
            return;
        }

        $stationCenterId = PollingStation::withoutGlobalScopes()
            ->whereKey($snapshot->polling_station_id)
            ->value('polling_center_id');

        if (
            (int) $stationCenterId
            !== (int) $snapshot->polling_center_id
        ) {
            throw new LogicException(
                'The turnout snapshot polling station must belong to the selected polling center.'
            );
        }
    }

    private static function ensureCountsAreValid(
        TurnoutSnapshot $snapshot
    ): void {
        if ($snapshot->turnout_count === null) {
            throw new LogicException(
                'A turnout count is required.'
            );
        }

        if ((int) $snapshot->turnout_count < 0) {
            throw new LogicException(
                'The turnout count cannot be negative.'
            );
        }

        if (
            $snapshot->registered_voters !== null
            && (int) $snapshot->registered_voters < 0
        ) {
            throw new LogicException(
                'The registered voter count cannot be negative.'
            );
        }

        if (
            $snapshot->registered_voters !== null
            && (int) $snapshot->turnout_count
                > (int) $snapshot->registered_voters
        ) {
            throw new LogicException(
                'The turnout count cannot exceed the registered voter count.'
            );
        }
    }
}
