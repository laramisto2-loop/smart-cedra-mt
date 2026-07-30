<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePollingCenterRequest;
use App\Http\Requests\UpdatePollingCenterRequest;
use App\Http\Resources\PollingCenterResource;
use App\Models\PollingCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PollingCenterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PollingCenter::class);

        $request->validate([
            'area_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $pollingCenters = PollingCenter::query()
            ->with('area.district.governorate')
            ->withCount('pollingStations')
            ->when(
                $request->filled('area_id'),
                fn ($query) => $query->where(
                    'area_id',
                    $request->integer('area_id')
                )
            )
            ->orderBy('name_en')
            ->paginate(20);

        return PollingCenterResource::collection($pollingCenters);
    }

    public function store(
        StorePollingCenterRequest $request
    ): JsonResponse {
        $pollingCenter = PollingCenter::create(
            $request->validated()
        );

        return (new PollingCenterResource(
            $pollingCenter->load('area.district.governorate')
                ->loadCount('pollingStations')
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        PollingCenter $pollingCenter
    ): PollingCenterResource {
        Gate::authorize('view', $pollingCenter);

        return new PollingCenterResource(
            $pollingCenter->load('area.district.governorate')
                ->loadCount('pollingStations')
        );
    }

    public function update(
        UpdatePollingCenterRequest $request,
        PollingCenter $pollingCenter
    ): PollingCenterResource {
        $pollingCenter->update($request->validated());

        return new PollingCenterResource(
            $pollingCenter->refresh()
                ->load('area.district.governorate')
                ->loadCount('pollingStations')
        );
    }

    public function destroy(
        PollingCenter $pollingCenter
    ): Response {
        Gate::authorize('delete', $pollingCenter);

        $pollingCenter->delete();

        return response()->noContent();
    }
}
