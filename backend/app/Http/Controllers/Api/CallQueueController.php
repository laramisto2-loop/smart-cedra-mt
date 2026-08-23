<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignCallQueueRequest;
use App\Http\Requests\StoreCallQueueRequest;
use App\Http\Requests\UpdateCallQueueRequest;
use App\Http\Resources\CallAssignmentResource;
use App\Http\Resources\CallQueueResource;
use App\Models\CallAssignment;
use App\Models\CallQueue;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallQueueController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', CallQueue::class);

        $tenantId = app(TenantContext::class)->id();

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'call_script_id' => [
                'nullable',
                'integer',
                Rule::exists('call_scripts', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'created_by_user_id' => [
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
            'priority' => [
                'nullable',
                Rule::in(CallQueue::PRIORITIES),
            ],
            'status' => [
                'nullable',
                Rule::in(CallQueue::STATUSES),
            ],
            'starts_from' => [
                'nullable',
                'date',
            ],
            'starts_to' => [
                'nullable',
                'date',
                'after_or_equal:starts_from',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $queues = CallQueue::query()
            ->with([
                'callScript.creator',
                'creator',
            ])
            ->withCount('assignments')
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim(
                        (string) $request->input('search')
                    );

                    $query->where(
                        function ($searchQuery) use ($search): void {
                            $searchQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
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
                $request->filled('call_script_id'),
                fn ($query) => $query->where(
                    'call_script_id',
                    $request->integer('call_script_id')
                )
            )
            ->when(
                $request->filled('created_by_user_id'),
                fn ($query) => $query->where(
                    'created_by_user_id',
                    $request->integer('created_by_user_id')
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
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->input('status')
                )
            )
            ->when(
                $request->filled('starts_from'),
                fn ($query) => $query->where(
                    'starts_at',
                    '>=',
                    $request->input('starts_from')
                )
            )
            ->when(
                $request->filled('starts_to'),
                fn ($query) => $query->where(
                    'starts_at',
                    '<=',
                    $request->input('starts_to')
                )
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return CallQueueResource::collection($queues);
    }

    public function store(
        StoreCallQueueRequest $request
    ): JsonResponse {
        $attributes = $request->validated();
        $attributes['created_by_user_id'] = $request->user()->id;
        $attributes['status'] = $attributes['status'] ?? 'draft';

        $callQueue = CallQueue::query()->create($attributes);

        return (new CallQueueResource(
            $this->loadCallQueue($callQueue)
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        CallQueue $callQueue
    ): CallQueueResource {
        Gate::authorize('view', $callQueue);

        return new CallQueueResource(
            $this->loadCallQueue($callQueue)
        );
    }

    public function update(
        UpdateCallQueueRequest $request,
        CallQueue $callQueue
    ): CallQueueResource {
        $callQueue->update($request->validated());

        return new CallQueueResource(
            $this->loadCallQueue($callQueue->refresh())
        );
    }

    public function assign(
        AssignCallQueueRequest $request,
        CallQueue $callQueue
    ): JsonResponse {
        $attributes = $request->validated();
        $contactIds = $attributes['contact_ids'];

        unset($attributes['contact_ids']);

        $assignments = DB::transaction(
            function () use (
                $request,
                $callQueue,
                $contactIds,
                $attributes
            ) {
                return collect($contactIds)
                    ->map(function ($contactId) use (
                        $request,
                        $callQueue,
                        $attributes
                    ): CallAssignment {
                        $assignmentAttributes = $attributes;
                        $assignmentAttributes['call_queue_id'] = (
                            $callQueue->id
                        );
                        $assignmentAttributes['contact_id'] = $contactId;
                        $assignmentAttributes['assigned_by_user_id'] = (
                            $request->user()->id
                        );
                        $assignmentAttributes['status'] = 'pending';
                        $assignmentAttributes['priority'] = (
                            $attributes['priority']
                            ?? $callQueue->priority
                        );

                        return CallAssignment::query()->create(
                            $assignmentAttributes
                        );
                    });
            }
        );

        $assignments->each(
            fn (CallAssignment $assignment) => $assignment->load([
                'callQueue',
                'contact',
                'assignee',
                'assigner',
            ])->loadCount('attempts')
        );

        return CallAssignmentResource::collection($assignments)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(
        CallQueue $callQueue
    ): Response {
        Gate::authorize('delete', $callQueue);

        $callQueue->delete();

        return response()->noContent();
    }

    private function loadCallQueue(
        CallQueue $callQueue
    ): CallQueue {
        $callQueue
            ->load([
                'callScript.creator',
                'creator',
                'assignments.contact',
                'assignments.assignee',
                'assignments.assigner',
            ])
            ->loadCount('assignments');

        $callQueue->assignments->each(
            fn (CallAssignment $assignment) => (
                $assignment->loadCount('attempts')
            )
        );

        return $callQueue;
    }
}
