<?php

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class TenantContext
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function user(): ?User
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User ? $user : null;
    }

    public function tenant(): ?Tenant
    {
        return $this->user()?->tenant;
    }

    public function id(): ?int
    {
        $tenantId = $this->user()?->tenant_id;

        return $tenantId === null ? null : (int) $tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->id() !== null && $this->tenant() !== null;
    }
}
