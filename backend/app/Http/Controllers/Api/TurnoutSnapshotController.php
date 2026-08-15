<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTurnoutSnapshotRequest;
use App\Http\Resources\TurnoutSnapshotResource;
use App\Models\PollingStation;
use App\Models\TurnoutSnapshot;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TurnoutSnapshotController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', TurnoutSnapshot::class);

        $tenantId = app(TenantContext::class)->id();
        $pollingCenterId = $request->input('polling_center_id');

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'source' => [
                'nullable',
                Rule::in(TurnoutSnapshot::SOURCES),
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
                Rule::exists('polling_stations', 'id')
                    ->where(function ($query) use (
                        $tenantId,
                        $pollingCenterId
                    ) {
                        $query->where('tenant_id', $tenantId);

                        if (filled($pollingCenterId)) {
                            $query->where(
                                'polling_center_id',
                                $pollingCenterId
                            );
                        }

                        return $query;
                    }),
            ],
            'captured_from' => [
                'nullable',
                'date',
            ],
            'captured_to' => [
                'nullable',
                'date',
                'after_or_equal:captured_from',
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

        $snapshots = TurnoutSnapshot::query()
            ->with([
                'reporter',
                'pollingCenter.area.district.governorate',
                'pollingStation.pollingCenter',
            ])
            ->when(
                ! $this->mayViewTenantSnapshots($user),
                fn ($query) => $query->where(
                    'reported_by_user_id',
                    $user->id
                )
            )
            ->when(
                $request->boolean('mine'),
                fn ($query) => $query->where(
                    'reported_by_user_id',
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
                        fn ($searchQuery) => $searchQuery
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
                    );
                }
            )
            ->when(
                $request->filled('source'),
                fn ($query) => $query->where(
                    'source',
                    $request->input('source')
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
                $request->filled('captured_from'),
                fn ($query) => $query->where(
                    'captured_at',
                    '>=',
                    $request->input('captured_from')
                )
            )
            ->when(
                $request->filled('captured_to'),
                fn ($query) => $query->where(
                    'captured_at',
                    '<=',
                    $request->input('captured_to')
                )
            )
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return TurnoutSnapshotResource::collection($snapshots);
    }

    public function store(
        StoreTurnoutSnapshotRequest $request
    ): JsonResponse {
        $attributes = $request->validated();

        $existing = TurnoutSnapshot::query()
            ->where(
                'client_uuid',
                $attributes['client_uuid']
            )
            ->first();

        if ($existing !== null) {
            Gate::authorize('view', $existing);

            return (new TurnoutSnapshotResource(
                $this->loadSnapshot($existing)
            ))->response();
        }

        $attributes['reported_by_user_id'] = $request->user()->id;
        $attributes['source'] = $this->mayViewTenantSnapshots(
            $request->user()
        )
            ? 'admin'
            : 'field';

        if (
            ! array_key_exists('registered_voters', $attributes)
            || $attributes['registered_voters'] === null
        ) {
            $attributes['registered_voters'] = (
                $this->registeredVotersFor(
                    (int) $attributes['polling_center_id'],
                    isset($attributes['polling_station_id'])
                        ? (int) $attributes['polling_station_id']
                        : null
                )
            );
        }

        $snapshot = TurnoutSnapshot::create($attributes);

        return (new TurnoutSnapshotResource(
            $this->loadSnapshot($snapshot->refresh())
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        TurnoutSnapshot $turnoutSnapshot
    ): TurnoutSnapshotResource {
        Gate::authorize('view', $turnoutSnapshot);

        return new TurnoutSnapshotResource(
            $this->loadSnapshot($turnoutSnapshot)
        );
    }

    public function series(
        Request $request
    ): JsonResponse {
        Gate::authorize('viewAny', TurnoutSnapshot::class);

        $tenantId = app(TenantContext::class)->id();
        $pollingCenterId = $request->input('polling_center_id');

        $request->validate([
            'polling_center_id' => [
                'required',
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
                Rule::exists('polling_stations', 'id')
                    ->where(function ($query) use (
                        $tenantId,
                        $pollingCenterId
                    ) {
                        $query->where('tenant_id', $tenantId);

                        if (filled($pollingCenterId)) {
                            $query->where(
                                'polling_center_id',
                                $pollingCenterId
                            );
                        }

                        return $query;
                    }),
            ],
            'captured_from' => [
                'nullable',
                'date',
            ],
            'captured_to' => [
                'nullable',
                'date',
                'after_or_equal:captured_from',
            ],
        ]);

        $user = $request->user();

        $query = TurnoutSnapshot::query()
            ->with([
                'reporter',
                'pollingCenter.area.district.governorate',
                'pollingStation.pollingCenter',
            ])
            ->where(
                'polling_center_id',
                $request->integer('polling_center_id')
            )
            ->when(
                ! $this->mayViewTenantSnapshots($user),
                fn ($snapshotQuery) => $snapshotQuery->where(
                    'reported_by_user_id',
                    $user->id
                )
            )
            ->when(
                $request->filled('polling_station_id'),
                fn ($snapshotQuery) => $snapshotQuery->where(
                    'polling_station_id',
                    $request->integer('polling_station_id')
                ),
                fn ($snapshotQuery) => $snapshotQuery->whereNull(
                    'polling_station_id'
                )
            )
            ->when(
                $request->filled('captured_from'),
                fn ($snapshotQuery) => $snapshotQuery->where(
                    'captured_at',
                    '>=',
                    $request->input('captured_from')
                )
            )
            ->when(
                $request->filled('captured_to'),
                fn ($snapshotQuery) => $snapshotQuery->where(
                    'captured_at',
                    '<=',
                    $request->input('captured_to')
                )
            )
            ->orderBy('captured_at')
            ->orderBy('id');

        $snapshots = $query->get();
        $latest = $snapshots->last();
        $previous = $snapshots->count() > 1
            ? $snapshots->get($snapshots->count() - 2)
            : null;

        $percentage = null;

        if (
            $latest !== null
            && $latest->registered_voters !== null
            && $latest->registered_voters > 0
        ) {
            $percentage = round(
                (
                    $latest->turnout_count
                    / $latest->registered_voters
                ) * 100,
                2
            );
        }

        return response()->json([
            'data' => TurnoutSnapshotResource::collection(
                $snapshots
            )->resolve($request),
            'meta' => [
                'polling_center_id' => $request->integer(
                    'polling_center_id'
                ),
                'polling_station_id' => (
                    $request->filled('polling_station_id')
                        ? $request->integer('polling_station_id')
                        : null
                ),
                'points_count' => $snapshots->count(),
                'latest_turnout_count' => (
                    $latest?->turnout_count
                ),
                'previous_turnout_count' => (
                    $previous?->turnout_count
                ),
                'change_since_previous' => (
                    $latest !== null && $previous !== null
                        ? $latest->turnout_count
                            - $previous->turnout_count
                        : null
                ),
                'registered_voters' => (
                    $latest?->registered_voters
                ),
                'turnout_percentage' => $percentage,
                'latest_captured_at' => (
                    $latest?->captured_at?->toISOString()
                ),
            ],
        ]);
    }

    private function mayViewTenantSnapshots(
        $user
    ): bool {
        return $user->hasRole('tenant_admin')
            || $user->hasRole('coordinator');
    }

    private function loadSnapshot(
        TurnoutSnapshot $turnoutSnapshot
    ): TurnoutSnapshot {
        return $turnoutSnapshot->load([
            'reporter',
            'pollingCenter.area.district.governorate',
            'pollingStation.pollingCenter',
        ]);
    }

    private function registeredVotersFor(
        int $pollingCenterId,
        ?int $pollingStationId
    ): ?int {
        if ($pollingStationId !== null) {
            $registeredVoters = PollingStation::query()
                ->whereKey($pollingStationId)
                ->value('registered_voters');

            return $registeredVoters === null
                ? null
                : (int) $registeredVoters;
        }

        $stations = PollingStation::query()
            ->where('polling_center_id', $pollingCenterId)
            ->whereNotNull('registered_voters');

        if (! $stations->exists()) {
            return null;
        }

        return (int) $stations->sum('registered_voters');
    }
}
