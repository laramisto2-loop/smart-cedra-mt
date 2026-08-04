<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            self::recordAudit(
                $model,
                'created',
                null,
                self::auditAttributes($model->getAttributes())
            );
        });

        static::updated(function (Model $model): void {
            $changes = self::auditAttributes(
                $model->getChanges()
            );

            if ($changes === []) {
                return;
            }

            $oldValues = [];

            foreach (array_keys($changes) as $attribute) {
                $oldValues[$attribute] = $model->getRawOriginal(
                    $attribute
                );
            }

            self::recordAudit(
                $model,
                'updated',
                $oldValues,
                $changes
            );
        });

        static::deleted(function (Model $model): void {
            self::recordAudit(
                $model,
                'deleted',
                self::auditAttributes($model->getAttributes()),
                null
            );
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(
            AuditLog::class,
            'auditable'
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private static function recordAudit(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues
    ): void {
        $tenantContext = app(TenantContext::class);
        $user = $tenantContext->user();
        $tenantId = $tenantContext->id();

        if ($user === null || $tenantId === null) {
            return;
        }

        if ((int) $model->getAttribute('tenant_id') !== $tenantId) {
            return;
        }

        $request = app()->bound('request')
            ? request()
            : null;

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function auditAttributes(array $attributes): array
    {
        unset(
            $attributes['created_at'],
            $attributes['updated_at']
        );

        return $attributes;
    }
}

// This records only authenticated actions, avoiding noise from migrations and seeders.
