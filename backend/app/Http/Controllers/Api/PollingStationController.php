<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePollingStationRequest;
use App\Http\Requests\UpdatePollingStationRequest;
use App\Http\Resources\PollingStationResource;
use App\Models\PollingStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PollingStationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PollingStation::class);

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'polling_center_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $pollingStations = PollingStation::query()
            ->with('pollingCenter.area.district.governorate')
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim((string) $request->input('search'));

                    $query->where(
                        function ($searchQuery) use ($search): void {
                            $searchQuery
                                ->where('station_number', 'like', "%{$search}%")
                                ->orWhere('name_en', 'like', "%{$search}%")
                                ->orWhere('name_ar', 'like', "%{$search}%")
                                ->orWhere('room', 'like', "%{$search}%");
                        }
                    );
                }
            )
            ->when(
                $request->filled('polling_center_id'),
                fn ($query) => $query->where(
                    'polling_center_id',
                    $request->integer('polling_center_id')
                )
            )
            ->orderBy('station_number')
            ->paginate(20);

        return PollingStationResource::collection($pollingStations);
    }

    public function store(
        StorePollingStationRequest $request
    ): JsonResponse {
        $pollingStation = PollingStation::create(
            $request->validated()
        );

        return (new PollingStationResource(
            $pollingStation->load(
                'pollingCenter.area.district.governorate'
            )
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        PollingStation $pollingStation
    ): PollingStationResource {
        Gate::authorize('view', $pollingStation);

        return new PollingStationResource(
            $pollingStation->load(
                'pollingCenter.area.district.governorate'
            )
        );
    }

    public function update(
        UpdatePollingStationRequest $request,
        PollingStation $pollingStation
    ): PollingStationResource {
        $pollingStation->update($request->validated());

        return new PollingStationResource(
            $pollingStation->refresh()->load(
                'pollingCenter.area.district.governorate'
            )
        );
    }

    public function destroy(
        PollingStation $pollingStation
    ): Response {
        Gate::authorize('delete', $pollingStation);

        $pollingStation->delete();

        return response()->noContent();
    }
}
