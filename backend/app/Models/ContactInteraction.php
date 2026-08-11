<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ContactInteraction extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const CHANNELS = [
        'phone',
        'sms',
        'whatsapp',
        'email',
        'in_person',
        'note',
    ];

    public const DIRECTIONS = [
        'inbound',
        'outbound',
        'internal',
    ];

    public const OUTCOMES = [
        'completed',
        'no_answer',
        'follow_up',
        'declined',
        'failed',
        'informational',
    ];

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'recorded_by_user_id',
        'channel',
        'direction',
        'outcome',
        'subject',
        'notes',
        'duration_seconds',
        'occurred_at',
        'consent_status_snapshot',
        'consent_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'occurred_at' => 'datetime',
            'consent_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (
            ContactInteraction $interaction
        ): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $interaction->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A contact interaction must belong to a tenant.'
                );
            }

            self::ensureContactBelongsToTenant(
                $interaction->contact_id,
                (int) $tenantId
            );

            self::ensureRecorderBelongsToTenant(
                $interaction->recorded_by_user_id,
                (int) $tenantId
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by_user_id'
        );
    }

    private static function ensureContactBelongsToTenant(
        int $contactId,
        int $tenantId
    ): void {
        $contactTenantId = Contact::withoutGlobalScopes()
            ->whereKey($contactId)
            ->value('tenant_id');

        if ((int) $contactTenantId !== $tenantId) {
            throw new LogicException(
                'The interaction contact must belong to the same tenant.'
            );
        }
    }

    private static function ensureRecorderBelongsToTenant(
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
                'The interaction recorder must belong to the same tenant.'
            );
        }
    }
}
