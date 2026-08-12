<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignIncidentRequest;
use App\Http\Requests\ReviewIncidentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IncidentController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Incident::class);

        $tenantId = app(TenantContext::class)->id();

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'category' => [
                'nullable',
                Rule::in(Incident::CATEGORIES),
            ],
            'severity' => [
                'nullable',
                Rule::in(Incident::SEVERITIES),
            ],
            'status' => [
                'nullable',
                Rule::in(Incident::STATUSES),
            ],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'reported_by_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'polling_center_id' => [
                'nullable',
                'integer',
                Rule::exists('polling_centers', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'polling_station_id' => [
                'nullable',
                'integer',
                Rule::exists('polling_stations', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'occurred_from' => [
                'nullable',
                'date',
            ],
            'occurred_to' => [
                'nullable',
                'date',
                'after_or_equal:occurred_from',
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

        $incidents = Incident::query()
            ->with([
                'reporter',
                'assignee',
                'reviewer',
                'campaignTask',
                'area.district.governorate',
                'pollingCenter.area.district.governorate',
                'pollingStation.pollingCenter',
            ])
            ->withCount('attachments')
            ->when(
                ! $user->hasPermission('incidents.review'),
                fn ($query) => $query->where(
                    fn ($accessible) => $accessible
                        ->where(
                            'reported_by_user_id',
                            $user->id
                        )
                        ->orWhere(
                            'assigned_to_user_id',
                            $user->id
                        )
                )
            )
            ->when(
                $request->boolean('mine'),
                fn ($query) => $query->where(
                    fn ($mine) => $mine
                        ->where(
                            'reported_by_user_id',
                            $user->id
                        )
                        ->orWhere(
                            'assigned_to_user_id',
                            $user->id
                        )
                )
            )
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim(
                        (string) $request->input('search')
                    );

                    $query->where(
                        fn ($searchQuery) => $searchQuery
                            ->where(
                                'reference_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'title',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            )
                    );
                }
            )
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where(
                    'category',
                    $request->input('category')
                )
            )
            ->when(
                $request->filled('severity'),
                fn ($query) => $query->where(
                    'severity',
                    $request->input('severity')
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
                $request->filled('reported_by_user_id'),
                fn ($query) => $query->where(
                    'reported_by_user_id',
                    $request->integer('reported_by_user_id')
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
                $request->filled('polling_center_id'),
                fn ($query) => $query->where(
                    'polling_center_id',
                    $request->integer('polling_center_id')
                )
            )
            ->when(
                $request->filled('polling_station_id'),
                fn ($query) => $query->where(
                    'polling_station_id',
                    $request->integer('polling_station_id')
                )
            )
            ->when(
                $request->filled('occurred_from'),
                fn ($query) => $query->where(
                    'occurred_at',
                    '>=',
                    $request->input('occurred_from')
                )
            )
            ->when(
                $request->filled('occurred_to'),
                fn ($query) => $query->where(
                    'occurred_at',
                    '<=',
                    $request->input('occurred_to')
                )
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return IncidentResource::collection($incidents);
    }

    public function store(
        StoreIncidentRequest $request
    ): JsonResponse {
        $attributes = $request->validated();

        if ($request->filled('client_uuid')) {
            $existing = Incident::query()
                ->where(
                    'client_uuid',
                    $request->input('client_uuid')
                )
                ->first();

            if ($existing !== null) {
                Gate::authorize('view', $existing);

                return (new IncidentResource(
                    $this->loadIncident($existing)
                ))->response();
            }
        }

        $attributes['reported_by_user_id'] = $request->user()->id;
        $attributes['status'] = 'submitted';
        $attributes['reported_at'] = now();
        $attributes['category'] = $attributes['category'] ?? 'general';
        $attributes['severity'] = $attributes['severity'] ?? 'medium';

        $incident = Incident::create($attributes);

        return (new IncidentResource(
            $this->loadIncident($incident->refresh())
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        Incident $incident
    ): IncidentResource {
        Gate::authorize('view', $incident);

        return new IncidentResource(
            $this->loadIncident($incident, true)
        );
    }

    public function update(
        UpdateIncidentRequest $request,
        Incident $incident
    ): IncidentResource {
        $attributes = $request->validated();

        $expectedVersion = Arr::pull(
            $attributes,
            'expected_sync_version'
        );

        $this->ensureSyncVersion(
            $incident,
            $expectedVersion
        );

        $incident->update($attributes);

        return new IncidentResource(
            $this->loadIncident($incident->refresh(), true)
        );
    }

    public function assign(
        AssignIncidentRequest $request,
        Incident $incident
    ): IncidentResource {
        $this->ensureSyncVersion(
            $incident,
            $request->validated('expected_sync_version')
        );

        $incident->update([
            'assigned_to_user_id' => $request->validated(
                'assigned_to_user_id'
            ),
        ]);

        return new IncidentResource(
            $this->loadIncident($incident->refresh(), true)
        );
    }

    public function review(
        ReviewIncidentRequest $request,
        Incident $incident
    ): IncidentResource {
        $this->ensureSyncVersion(
            $incident,
            $request->validated('expected_sync_version')
        );

        $incident->update([
            'status' => $request->validated('status'),
            'reviewed_by_user_id' => $request->user()->id,
            'resolution_notes' => $request->validated(
                'resolution_notes'
            ),
        ]);

        return new IncidentResource(
            $this->loadIncident($incident->refresh(), true)
        );
    }

    public function destroy(
        Incident $incident
    ): Response {
        Gate::authorize('delete', $incident);

        $incident->delete();

        return response()->noContent();
    }

    private function ensureSyncVersion(
        Incident $incident,
        mixed $expectedVersion
    ): void {
        if (
            $expectedVersion !== null
            && (int) $expectedVersion !== (int) $incident->sync_version
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'The incident changed since it was last synchronized. Refresh it before saving again.'
            );
        }
    }

    private function loadIncident(
        Incident $incident,
        bool $withAttachments = false
    ): Incident {
        $relationships = [
            'reporter',
            'assignee',
            'reviewer',
            'campaignTask',
            'area.district.governorate',
            'pollingCenter.area.district.governorate',
            'pollingStation.pollingCenter',
        ];

        if ($withAttachments) {
            $relationships[] = 'attachments.uploader';
        }

        return $incident
            ->load($relationships)
            ->loadCount('attachments');
    }
}
