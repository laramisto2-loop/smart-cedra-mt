<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCallAssignmentRequest;
use App\Http\Resources\CallAssignmentResource;
use App\Models\CallAssignment;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallAssignmentController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', CallAssignment::class);

        $tenantId = app(TenantContext::class)->id();

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'call_queue_id' => [
                'nullable',
                'integer',
                Rule::exists('call_queues', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'status' => [
                'nullable',
                Rule::in(CallAssignment::STATUSES),
            ],
            'priority' => [
                'nullable',
                Rule::in(CallAssignment::PRIORITIES),
            ],
            'scheduled_from' => [
                'nullable',
                'date',
            ],
            'scheduled_to' => [
                'nullable',
                'date',
                'after_or_equal:scheduled_from',
            ],
            'mine' => [
                'nullable',
                'boolean',
            ],
            'unassigned' => [
                'nullable',
                'boolean',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $user = $request->user();

        $assignments = CallAssignment::query()
            ->with([
                'callQueue',
                'contact',
                'assignee',
                'assigner',
            ])
            ->withCount('attempts')
            ->when(
                ! $this->mayManageAssignments($user),
                fn ($query) => $query->where(
                    'assigned_to_user_id',
                    $user->id
                )
            )
            ->when(
                $request->boolean('mine'),
                fn ($query) => $query->where(
                    'assigned_to_user_id',
                    $user->id
                )
            )
            ->when(
                $request->boolean('unassigned')
                && $this->mayManageAssignments($user),
                fn ($query) => $query->whereNull(
                    'assigned_to_user_id'
                )
            )
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim(
                        (string) $request->input('search')
                    );

                    $query->where(
                        function ($searchQuery) use ($search): void {
                            $searchQuery
                                ->whereHas(
                                    'contact',
                                    function ($contactQuery) use (
                                        $search
                                    ): void {
                                        $contactQuery
                                            ->where(
                                                'reference_code',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'first_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'last_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'phone',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
                                )
                                ->orWhereHas(
                                    'callQueue',
                                    function ($queueQuery) use (
                                        $search
                                    ): void {
                                        $queueQuery
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'code',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('call_queue_id'),
                fn ($query) => $query->where(
                    'call_queue_id',
                    $request->integer('call_queue_id')
                )
            )
            ->when(
                $request->filled('contact_id'),
                fn ($query) => $query->where(
                    'contact_id',
                    $request->integer('contact_id')
                )
            )
            ->when(
                $request->filled('assigned_to_user_id'),
                fn ($query) => $query->where(
                    'assigned_to_user_id',
                    $request->integer('assigned_to_user_id')
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->input('status')
                )
            )
            ->when(
                $request->filled('priority'),
                fn ($query) => $query->where(
                    'priority',
                    $request->input('priority')
                )
            )
            ->when(
                $request->filled('scheduled_from'),
                fn ($query) => $query->where(
                    'scheduled_for',
                    '>=',
                    $request->input('scheduled_from')
                )
            )
            ->when(
                $request->filled('scheduled_to'),
                fn ($query) => $query->where(
                    'scheduled_for',
                    '<=',
                    $request->input('scheduled_to')
                )
            )
            ->orderByRaw(
                "case priority
                    when 'urgent' then 1
                    when 'high' then 2
                    when 'normal' then 3
                    when 'low' then 4
                    else 5
                end"
            )
            ->orderByRaw(
                'case when scheduled_for is null then 1 else 0 end'
            )
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return CallAssignmentResource::collection($assignments);
    }

    public function show(
        CallAssignment $callAssignment
    ): CallAssignmentResource {
        Gate::authorize('view', $callAssignment);

        return new CallAssignmentResource(
            $this->loadCallAssignment($callAssignment)
        );
    }

    public function claim(
        Request $request,
        CallAssignment $callAssignment
    ): CallAssignmentResource {
        $claimedAssignment = DB::transaction(
            function () use (
                $request,
                $callAssignment
            ): CallAssignment {
                $lockedAssignment = CallAssignment::query()
                    ->whereKey($callAssignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                Gate::forUser($request->user())->authorize(
                    'claim',
                    $lockedAssignment
                );

                $lockedAssignment->update([
                    'assigned_to_user_id' => $request->user()->id,
                    'status' => 'in_progress',
                ]);

                return $lockedAssignment;
            }
        );

        return new CallAssignmentResource(
            $this->loadCallAssignment(
                $claimedAssignment->refresh()
            )
        );
    }

    public function update(
        UpdateCallAssignmentRequest $request,
        CallAssignment $callAssignment
    ): CallAssignmentResource {
        $callAssignment->update($request->validated());

        return new CallAssignmentResource(
            $this->loadCallAssignment(
                $callAssignment->refresh()
            )
        );
    }

    private function mayManageAssignments(
        $user
    ): bool {
        return $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator');
    }

    private function loadCallAssignment(
        CallAssignment $callAssignment
    ): CallAssignment {
        return $callAssignment
            ->load([
                'callQueue',
                'contact',
                'assignee',
                'assigner',
                'attempts.performer',
                'attempts.followUpTask',
            ])
            ->loadCount('attempts');
    }
}
