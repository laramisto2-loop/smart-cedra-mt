<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlatformTenantRequest;
use App\Http\Requests\UpdatePlatformTenantRequest;
use App\Http\Requests\UpdatePlatformTenantStatusRequest;
use App\Http\Resources\PlatformTenantResource;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PlatformTenantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'nullable',
                'string',
                'in:active,suspended',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = $this->tenantQuery();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $tenants = $query
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return PlatformTenantResource::collection($tenants);
    }

    public function store(
        StorePlatformTenantRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $tenant = DB::transaction(function () use ($validated): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'status' => $validated['status'] ?? 'active',
            ]);

            $tenant->settings()->create([
                'brand_name' => $validated['brand_name']
                    ?? $validated['name'],
                'primary_color' => $validated['primary_color']
                    ?? '#0d6efd',
                'timezone' => $validated['timezone']
                    ?? 'Asia/Beirut',
            ]);

            $administrator = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['admin_password'],
            ]);

            (new RbacSeeder)->run();

            $administratorRole = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'tenant_admin')
                ->firstOrFail();

            $administrator->assignRole($administratorRole);

            return $tenant;
        });

        return (new PlatformTenantResource(
            $this->loadTenant($tenant)
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Tenant $tenant): PlatformTenantResource
    {
        return new PlatformTenantResource(
            $this->loadTenant($tenant)
        );
    }

    public function update(
        UpdatePlatformTenantRequest $request,
        Tenant $tenant
    ): PlatformTenantResource {
        $validated = $request->validated();

        DB::transaction(function () use ($tenant, $validated): void {
            $tenantAttributes = Arr::only($validated, [
                'name',
                'slug',
            ]);

            if ($tenantAttributes !== []) {
                $tenant->update($tenantAttributes);
            }

            $settingAttributes = Arr::only($validated, [
                'brand_name',
                'primary_color',
                'timezone',
            ]);

            if ($settingAttributes !== []) {
                $tenant->settings()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                    ],
                    $settingAttributes
                );
            }
        });

        return new PlatformTenantResource(
            $this->loadTenant($tenant->fresh())
        );
    }

    public function updateStatus(
        UpdatePlatformTenantStatusRequest $request,
        Tenant $tenant
    ): PlatformTenantResource {
        $status = $request->validated('status');

        DB::transaction(function () use ($tenant, $status): void {
            $tenant->update([
                'status' => $status,
            ]);

            if ($status === 'suspended') {
                $tenant->users()
                    ->get()
                    ->each(function (User $user): void {
                        $user->tokens()->delete();
                    });
            }
        });

        return new PlatformTenantResource(
            $this->loadTenant($tenant->fresh())
        );
    }

    private function tenantQuery(): Builder
    {
        return Tenant::query()
            ->with('settings')
            ->withCount([
                'users',
                'roles',
                'users as administrator_count' => function (
                    Builder $query
                ): void {
                    $query->whereHas(
                        'roles',
                        function (Builder $roleQuery): void {
                            $roleQuery->where(
                                'roles.slug',
                                'tenant_admin'
                            );
                        }
                    );
                },
            ])
            ->with([
                'users' => function ($query): void {
                    $query
                        ->whereHas(
                            'roles',
                            function (Builder $roleQuery): void {
                                $roleQuery->where(
                                    'roles.slug',
                                    'tenant_admin'
                                );
                            }
                        )
                        ->with('roles')
                        ->orderBy('name');
                },
            ]);
    }

    private function loadTenant(Tenant $tenant): Tenant
    {
        return $this->tenantQuery()->findOrFail($tenant->id);
    }
}
