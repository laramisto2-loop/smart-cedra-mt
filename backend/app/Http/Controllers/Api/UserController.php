<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\SyncUserRolesRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->with('roles.permissions')
            ->when(
                $validated['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        }
                    );
                }
            )
            ->when(
                $validated['role_id'] ?? null,
                function (Builder $query, int $roleId): void {
                    $query->whereHas(
                        'roles',
                        fn (Builder $query) => $query->where(
                            'roles.id',
                            $roleId
                        )
                    );
                }
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $roleIds = $validated['role_ids'];

        unset($validated['role_ids']);

        $user = DB::transaction(
            function () use ($request, $validated, $roleIds): User {
                $user = User::query()->create([
                    ...$validated,
                    'tenant_id' => $request->user()->tenant_id,
                ]);

                $user->roles()->sync($roleIds);

                return $user;
            }
        );

        $user->load('roles.permissions');

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        $user->load('roles.permissions');

        return new UserResource($user);
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): UserResource {
        $user->fill($request->validated());
        $user->save();

        $user->load('roles.permissions');

        return new UserResource($user);
    }

    public function syncRoles(
        SyncUserRolesRequest $request,
        User $user
    ): UserResource {
        $roleIds = $request->validated('role_ids');

        $this->ensureTenantKeepsAdministrator($user, $roleIds);

        DB::transaction(
            fn () => $user->roles()->sync($roleIds)
        );

        $user->load('roles.permissions');

        return new UserResource($user);
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $this->ensureTenantKeepsAdministrator($user, []);

        $user->delete();

        return response()->noContent();
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function ensureTenantKeepsAdministrator(
        User $targetUser,
        array $roleIds
    ): void {
        $currentlyAdministrator = $targetUser->roles()
            ->where('roles.slug', 'tenant_admin')
            ->exists();

        if (! $currentlyAdministrator) {
            return;
        }

        $keepsAdministratorRole = Role::query()
            ->where('tenant_id', $targetUser->tenant_id)
            ->where('slug', 'tenant_admin')
            ->whereIn('id', $roleIds)
            ->exists();

        if ($keepsAdministratorRole) {
            return;
        }

        $administratorCount = User::query()
            ->where('tenant_id', $targetUser->tenant_id)
            ->whereHas(
                'roles',
                fn (Builder $query) => $query->where(
                    'roles.slug',
                    'tenant_admin'
                )
            )
            ->count();

        if ($administratorCount <= 1) {
            throw ValidationException::withMessages([
                'role_ids' => [
                    'Every tenant must retain at least one tenant administrator.',
                ],
            ]);
        }
    }
}
