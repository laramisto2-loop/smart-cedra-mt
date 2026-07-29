<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait EnsuresParentBelongsToTenant
{
    abstract protected function tenantParentClass(): string;

    abstract protected function tenantParentForeignKey(): string;

    public static function bootEnsuresParentBelongsToTenant(): void
    {
        static::saving(function (Model $model): void {
            $tenantId = app(TenantContext::class)->id()
                ?? $model->getAttribute('tenant_id');

            $parentClass = $model->tenantParentClass();
            $parentId = $model->getAttribute(
                $model->tenantParentForeignKey()
            );

            $parentTenantId = $parentClass::withoutGlobalScopes()
                ->whereKey($parentId)
                ->value('tenant_id');

            if (
                $tenantId === null
                || $parentTenantId === null
                || (int) $tenantId !== (int) $parentTenantId
            ) {
                throw new LogicException(
                    'The parent geography record must belong to the same tenant.'
                );
            }
        });
    }
}
