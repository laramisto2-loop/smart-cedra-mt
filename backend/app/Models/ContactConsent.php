<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ContactConsent extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const CHANNELS = [
        'phone',
        'sms',
        'whatsapp',
        'email',
    ];

    public const STATUSES = [
        'unknown',
        'granted',
        'denied',
        'revoked',
    ];

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'recorded_by_user_id',
        'channel',
        'status',
        'source',
        'consented_at',
        'revoked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (
            ContactConsent $consent
        ): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $consent->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A contact consent must belong to a tenant.'
                );
            }

            self::ensureContactBelongsToTenant(
                $consent->contact_id,
                (int) $tenantId
            );

            self::ensureRecorderBelongsToTenant(
                $consent->recorded_by_user_id,
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
                'The consent contact must belong to the same tenant.'
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
                'The consent recorder must belong to the same tenant.'
            );
        }
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(
            OutboundMessage::class,
            'contact_consent_id'
        );
    }
}
// every messaging record can be navigated safely from its tenant, creator, sender, contact, and exact consent decision
