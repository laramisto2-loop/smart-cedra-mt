<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AreaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Area::class);

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'district_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $areas = Area::query()
            ->with('district.governorate')
            ->withCount('pollingCenters')
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim((string) $request->input('search'));

                    $query->where(
                        function ($searchQuery) use ($search): void {
                            $searchQuery
                                ->where('name_en', 'like', "%{$search}%")
                                ->orWhere('name_ar', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        }
                    );
                }
            )
            ->when(
                $request->filled('district_id'),
                fn ($query) => $query->where(
                    'district_id',
                    $request->integer('district_id')
                )
            )
            ->orderBy('name_en')
            ->paginate(20);

        return AreaResource::collection($areas);
    }

    public function store(StoreAreaRequest $request): JsonResponse
    {
        $area = Area::create($request->validated());

        return (new AreaResource(
            $area->load('district.governorate')
                ->loadCount('pollingCenters')
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Area $area): AreaResource
    {
        Gate::authorize('view', $area);

        return new AreaResource(
            $area->load('district.governorate')
                ->loadCount('pollingCenters')
        );
    }

    public function update(
        UpdateAreaRequest $request,
        Area $area
    ): AreaResource {
        $area->update($request->validated());

        return new AreaResource(
            $area->refresh()
                ->load('district.governorate')
                ->loadCount('pollingCenters')
        );
    }

    public function destroy(Area $area): Response
    {
        Gate::authorize('delete', $area);

        $area->delete();

        return response()->noContent();
    }
}
