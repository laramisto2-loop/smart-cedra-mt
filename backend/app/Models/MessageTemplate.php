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

class MessageTemplate extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const CHANNELS = [
        'sms',
        'whatsapp',
    ];

    public const CATEGORIES = [
        'marketing',
        'utility',
        'authentication',
    ];

    public const STATUSES = [
        'draft',
        'pending_approval',
        'approved',
        'rejected',
        'inactive',
    ];

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'name',
        'code',
        'channel',
        'provider',
        'provider_template_name',
        'language_code',
        'category',
        'body',
        'variables',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MessageTemplate $template): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $template->tenant_id;

            if ($tenantId === null) {
                throw new LogicException(
                    'A message template must belong to a tenant.'
                );
            }

            if (
                ! in_array(
                    $template->channel,
                    self::CHANNELS,
                    true
                )
            ) {
                throw new LogicException(
                    'The message template channel is invalid.'
                );
            }

            if (
                ! in_array(
                    $template->category,
                    self::CATEGORIES,
                    true
                )
            ) {
                throw new LogicException(
                    'The message template category is invalid.'
                );
            }

            if (
                ! in_array(
                    $template->status,
                    self::STATUSES,
                    true
                )
            ) {
                throw new LogicException(
                    'The message template status is invalid.'
                );
            }

            self::ensureCreatorBelongsToTenant(
                $template->created_by_user_id,
                (int) $tenantId
            );

            if (
                $template->status === 'approved'
                && $template->approved_at === null
            ) {
                $template->approved_at = now();
            }

            if ($template->status !== 'approved') {
                $template->approved_at = null;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }

    private static function ensureCreatorBelongsToTenant(
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
                'The message template creator must belong to the same tenant.'
            );
        }
    }
}
//This establishes tenant isolation, approved channel/category/status values, creator validation, approval timestamps, audit logging, and relationships