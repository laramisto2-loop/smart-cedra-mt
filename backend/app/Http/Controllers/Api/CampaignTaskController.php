<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignCampaignTaskRequest;
use App\Http\Requests\CompleteCampaignTaskRequest;
use App\Http\Requests\StoreCampaignTaskRequest;
use App\Http\Requests\UpdateCampaignTaskRequest;
use App\Http\Resources\CampaignTaskResource;
use App\Models\CampaignTask;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampaignTaskController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', CampaignTask::class);

        $tenantId = app(TenantContext::class)->id();

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'type' => [
                'nullable',
                'string',
                Rule::in(CampaignTask::TYPES),
            ],
            'priority' => [
                'nullable',
                'string',
                Rule::in(CampaignTask::PRIORITIES),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(CampaignTask::STATUSES),
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
            'area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'due_from' => [
                'nullable',
                'date',
            ],
            'due_to' => [
                'nullable',
                'date',
                'after_or_equal:due_from',
            ],
            'mine' => [
                'nullable',
                'boolean',
            ],
            'overdue' => [
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

        $tasks = CampaignTask::query()
            ->with([
                'contact',
                'area.district.governorate',
                'creator',
                'assignee',
            ])
            ->when(
                ! $user->hasPermission('tasks.update'),
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
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim(
                        (string) $request->input('search')
                    );

                    $query->where(
                        function ($searchQuery) use ($search): void {
                            $searchQuery
                                ->where(
                                    'title',
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
                $request->filled('type'),
                fn ($query) => $query->where(
                    'type',
                    $request->input('type')
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
                $request->filled('assigned_to_user_id'),
                fn ($query) => $query->where(
                    'assigned_to_user_id',
                    $request->integer('assigned_to_user_id')
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
                $request->filled('area_id'),
                fn ($query) => $query->where(
                    'area_id',
                    $request->integer('area_id')
                )
            )
            ->when(
                $request->filled('due_from'),
                fn ($query) => $query->whereDate(
                    'due_at',
                    '>=',
                    $request->input('due_from')
                )
            )
            ->when(
                $request->filled('due_to'),
                fn ($query) => $query->whereDate(
                    'due_at',
                    '<=',
                    $request->input('due_to')
                )
            )
            ->when(
                $request->boolean('overdue'),
                fn ($query) => $query
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->whereNotIn(
                        'status',
                        ['completed', 'cancelled']
                    )
            )
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return CampaignTaskResource::collection($tasks);
    }

    public function store(
        StoreCampaignTaskRequest $request
    ): JsonResponse {
        $attributes = $request->validated();

        if (
            array_key_exists('assigned_to_user_id', $attributes)
            && ! $request->user()->hasPermission('tasks.assign')
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $attributes['created_by_user_id'] = $request->user()->id;
        $attributes['status'] = $attributes['status'] ?? 'pending';

        $task = CampaignTask::create($attributes);

        return (new CampaignTaskResource(
            $this->loadTask($task)
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        CampaignTask $campaignTask
    ): CampaignTaskResource {
        Gate::authorize('view', $campaignTask);

        return new CampaignTaskResource(
            $this->loadTask($campaignTask)
        );
    }

    public function update(
        UpdateCampaignTaskRequest $request,
        CampaignTask $campaignTask
    ): CampaignTaskResource {
        $campaignTask->update($request->validated());

        return new CampaignTaskResource(
            $this->loadTask($campaignTask->refresh())
        );
    }

    public function assign(
        AssignCampaignTaskRequest $request,
        CampaignTask $campaignTask
    ): CampaignTaskResource {
        $campaignTask->update([
            'assigned_to_user_id' => $request->validated(
                'assigned_to_user_id'
            ),
        ]);

        return new CampaignTaskResource(
            $this->loadTask($campaignTask->refresh())
        );
    }

    public function complete(
        CompleteCampaignTaskRequest $request,
        CampaignTask $campaignTask
    ): CampaignTaskResource {
        if ($campaignTask->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => [
                    'A cancelled task cannot be completed.',
                ],
            ]);
        }

        $campaignTask->update([
            'status' => 'completed',
            'completion_notes' => $request->validated(
                'completion_notes'
            ),
        ]);

        return new CampaignTaskResource(
            $this->loadTask($campaignTask->refresh())
        );
    }

    public function destroy(
        CampaignTask $campaignTask
    ): Response {
        Gate::authorize('delete', $campaignTask);

        $campaignTask->delete();

        return response()->noContent();
    }

    private function loadTask(
        CampaignTask $campaignTask
    ): CampaignTask {
        return $campaignTask->load([
            'contact',
            'area.district.governorate',
            'creator',
            'assignee',
        ]);
    }
}
