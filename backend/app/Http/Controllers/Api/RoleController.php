<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PROTECTED_ROLE_SLUGS = [
        'tenant_admin',
        'coordinator',
        'field_agent',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'permission_id' => [
                'nullable',
                'integer',
                Rule::exists('permissions', 'id'),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $roles = Role::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('permissions')
            ->withCount('users')
            ->when(
                $validated['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere(
                                    'description',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $validated['permission_id'] ?? null,
                function (Builder $query, int $permissionId): void {
                    $query->whereHas(
                        'permissions',
                        fn (Builder $query) => $query->where(
                            'permissions.id',
                            $permissionId
                        )
                    );
                }
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return RoleResource::collection($roles);
    }

    public function permissions(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $permissions = Permission::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return PermissionResource::collection($permissions);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'];

        unset($validated['permission_ids']);

        $role = DB::transaction(
            function () use (
                $request,
                $validated,
                $permissionIds
            ): Role {
                $role = Role::query()->create([
                    ...$validated,
                    'tenant_id' => $request->user()->tenant_id,
                ]);

                $role->permissions()->sync($permissionIds);

                return $role;
            }
        );

        $role->load('permissions');
        $role->loadCount('users');

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        $role->load('permissions');
        $role->loadCount('users');

        return new RoleResource($role);
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role
    ): RoleResource {
        $validated = $request->validated();

        if (
            in_array(
                $role->slug,
                self::PROTECTED_ROLE_SLUGS,
                true
            )
            && isset($validated['slug'])
            && $validated['slug'] !== $role->slug
        ) {
            throw ValidationException::withMessages([
                'slug' => [
                    'The slug of a standard system role cannot be changed.',
                ],
            ]);
        }

        $role->fill($validated);
        $role->save();

        $role->load('permissions');
        $role->loadCount('users');

        return new RoleResource($role);
    }

    public function syncPermissions(
        SyncRolePermissionsRequest $request,
        Role $role
    ): RoleResource {
        $permissionIds = $request->validated('permission_ids');

        $this->ensureAdministratorPermissions(
            $role,
            $permissionIds
        );

        DB::transaction(
            fn () => $role->permissions()->sync($permissionIds)
        );

        $role->load('permissions');
        $role->loadCount('users');

        return new RoleResource($role);
    }

    public function destroy(Role $role): Response
    {
        $this->authorize('delete', $role);

        if (
            in_array(
                $role->slug,
                self::PROTECTED_ROLE_SLUGS,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'Standard system roles cannot be deleted.',
                ],
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => [
                    'Remove this role from all users before deleting it.',
                ],
            ]);
        }

        $role->delete();

        return response()->noContent();
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    private function ensureAdministratorPermissions(
        Role $role,
        array $permissionIds
    ): void {
        if ($role->slug !== 'tenant_admin') {
            return;
        }

        $requiredPermissionIds = Permission::query()
            ->whereIn('slug', [
                'roles.manage',
                'users.manage',
            ])
            ->pluck('id');

        if ($requiredPermissionIds->diff($permissionIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permission_ids' => [
                    'The tenant administrator must retain user and role management permissions.',
                ],
            ]);
        }
    }
}
