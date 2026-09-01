<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingRequest;
use App\Http\Resources\TenantSettingResource;
use App\Models\TenantSetting;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TenantSettingController extends Controller
{
    public function show(
        Request $request,
        TenantContext $tenantContext
    ): TenantSettingResource {
        if (! $request->user()->hasPermission('settings.manage')) {
            throw new AccessDeniedHttpException;
        }

        return new TenantSettingResource(
            $this->settings($tenantContext, false)
        );
    }

    public function update(
        UpdateTenantSettingRequest $request,
        TenantContext $tenantContext
    ): TenantSettingResource {
        $settings = $this->settings($tenantContext, true);
        $settings->update($request->validated());

        return new TenantSettingResource($settings->refresh());
    }

    private function settings(
        TenantContext $tenantContext,
        bool $createIfMissing
    ): TenantSetting {
        $settings = TenantSetting::query()->first();

        if ($settings !== null) {
            return $settings;
        }

        $defaults = [
            'tenant_id' => $tenantContext->id(),
            'brand_name' => $tenantContext->user()?->tenant?->name,
            'primary_color' => '#167ead',
            'timezone' => 'Asia/Beirut',
        ];

        return $createIfMissing
            ? TenantSetting::query()->create($defaults)
            : new TenantSetting($defaults);
    }
}
