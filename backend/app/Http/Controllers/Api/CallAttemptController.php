<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCallAttemptRequest;
use App\Http\Resources\CallAttemptResource;
use App\Models\CallAssignment;
use App\Models\CallAttempt;
use App\Models\CampaignTask;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CallAttemptController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', CallAttempt::class);

        $tenantId = app(TenantContext::class)->id();

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'call_assignment_id' => [
                'nullable',
                'integer',
                Rule::exists('call_assignments', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'performed_by_user_id' => [
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
            'outcome' => [
                'nullable',
                Rule::in(CallAttempt::OUTCOMES),
            ],
            'attempted_from' => [
                'nullable',
                'date',
            ],
            'attempted_to' => [
                'nullable',
                'date',
                'after_or_equal:attempted_from',
            ],
            'mine' => [
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

        $attempts = CallAttempt::query()
            ->with([
                'performer',
                'followUpTask.contact',
                'followUpTask.area',
                'followUpTask.creator',
                'followUpTask.assignee',
            ])
            ->when(
                ! $this->mayManageAttempts($user),
                fn ($query) => $query->where(
                    function ($accessQuery) use ($user): void {
                        $accessQuery
                            ->where(
                                'performed_by_user_id',
                                $user->id
                            )
                            ->orWhereHas(
                                'callAssignment',
                                fn ($assignmentQuery) => (
                                    $assignmentQuery->where(
                                        'assigned_to_user_id',
                                        $user->id
                                    )
                                )
                            );
                    }
                )
            )
            ->when(
                $request->boolean('mine'),
                fn ($query) => $query->where(
                    'performed_by_user_id',
                    $user->id
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
                                ->where(
                                    'reference_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'notes',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'callAssignment.contact',
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
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('call_assignment_id'),
                fn ($query) => $query->where(
                    'call_assignment_id',
                    $request->integer('call_assignment_id')
                )
            )
            ->when(
                $request->filled('performed_by_user_id'),
                fn ($query) => $query->where(
                    'performed_by_user_id',
                    $request->integer('performed_by_user_id')
                )
            )
            ->when(
                $request->filled('outcome'),
                fn ($query) => $query->where(
                    'outcome',
                    $request->input('outcome')
                )
            )
            ->when(
                $request->filled('attempted_from'),
                fn ($query) => $query->where(
                    'attempted_at',
                    '>=',
                    $request->input('attempted_from')
                )
            )
            ->when(
                $request->filled('attempted_to'),
                fn ($query) => $query->where(
                    'attempted_at',
                    '<=',
                    $request->input('attempted_to')
                )
            )
            ->orderByDesc('attempted_at')
            ->orderByDesc('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return CallAttemptResource::collection($attempts);
    }

    public function store(
        StoreCallAttemptRequest $request
    ): JsonResponse {
        $attributes = $request->validated();

        $existing = CallAttempt::query()
            ->where(
                'client_uuid',
                $attributes['client_uuid']
            )
            ->first();

        if ($existing !== null) {
            Gate::authorize('view', $existing);

            return (new CallAttemptResource(
                $this->loadCallAttempt($existing)
            ))->response();
        }

        $attempt = DB::transaction(
            function () use (
                $request,
                $attributes
            ): CallAttempt {
                $assignment = CallAssignment::query()
                    ->with('contact')
                    ->whereKey(
                        $attributes['call_assignment_id']
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureAssignmentCanBeAttempted(
                    $request,
                    $assignment
                );

                if ($assignment->assigned_to_user_id === null) {
                    $assignment->assigned_to_user_id = (
                        $request->user()->id
                    );
                }

                $followUpTask = null;

                if (
                    $attributes['outcome']
                    === 'callback_requested'
                ) {
                    $followUpTask = $this->createFollowUpTask(
                        $request,
                        $assignment,
                        $attributes
                    );
                }

                $attemptAttributes = $attributes;
                $attemptAttributes['performed_by_user_id'] = (
                    $request->user()->id
                );
                $attemptAttributes['follow_up_task_id'] = (
                    $followUpTask?->id
                );

                $attempt = CallAttempt::create(
                    $attemptAttributes
                );

                $assignment->last_attempted_at = (
                    $attempt->attempted_at
                );

                if ($attempt->outcome === 'completed') {
                    $assignment->status = 'completed';
                } elseif ($assignment->status === 'pending') {
                    $assignment->status = 'in_progress';
                }

                $assignment->save();

                return $attempt;
            }
        );

        return (new CallAttemptResource(
            $this->loadCallAttempt($attempt->refresh())
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        CallAttempt $callAttempt
    ): CallAttemptResource {
        Gate::authorize('view', $callAttempt);

        return new CallAttemptResource(
            $this->loadCallAttempt($callAttempt)
        );
    }

    private function ensureAssignmentCanBeAttempted(
        StoreCallAttemptRequest $request,
        CallAssignment $assignment
    ): void {
        if (
            ! in_array(
                $assignment->status,
                ['pending', 'in_progress'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'call_assignment_id' => (
                    'Only pending or in-progress assignments can receive a call attempt.'
                ),
            ]);
        }

        if ($this->mayManageAttempts($request->user())) {
            return;
        }

        if (
            (int) $assignment->assigned_to_user_id
            !== (int) $request->user()->id
        ) {
            throw ValidationException::withMessages([
                'call_assignment_id' => (
                    'You may only record attempts for assignments allocated to you.'
                ),
            ]);
        }
    }

    private function createFollowUpTask(
        StoreCallAttemptRequest $request,
        CallAssignment $assignment,
        array $attributes
    ): CampaignTask {
        $contact = $assignment->contact;

        $contactName = trim(
            implode(' ', array_filter([
                $contact?->first_name,
                $contact?->last_name,
            ]))
        );

        if ($contactName === '') {
            $contactName = $contact?->reference_code
                ?? "assignment #{$assignment->id}";
        }

        $description = (
            'A callback was requested during call assignment #'
            .$assignment->id.'.'
        );

        if (filled($attributes['notes'] ?? null)) {
            $description .= "\n\nCall notes: "
                .$attributes['notes'];
        }

        return CampaignTask::create([
            'contact_id' => $assignment->contact_id,
            'area_id' => $contact?->area_id,
            'created_by_user_id' => $request->user()->id,
            'assigned_to_user_id' => (
                $assignment->assigned_to_user_id
                ?? $request->user()->id
            ),
            'title' => "Return call to {$contactName}",
            'description' => $description,
            'type' => 'follow_up',
            'priority' => $assignment->priority,
            'status' => 'pending',
            'due_at' => $attributes['follow_up_at'],
        ]);
    }

    private function mayManageAttempts(
        $user
    ): bool {
        return $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator');
    }

    private function loadCallAttempt(
        CallAttempt $callAttempt
    ): CallAttempt {
        return $callAttempt->load([
            'performer',
            'followUpTask.contact',
            'followUpTask.area.district.governorate',
            'followUpTask.creator',
            'followUpTask.assignee',
        ]);
    }
}
