<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDistrictRequest;
use App\Http\Requests\UpdateDistrictRequest;
use App\Http\Resources\DistrictResource;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DistrictController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', District::class);

        $request->validate([
            'governorate_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $districts = District::query()
            ->with('governorate')
            ->withCount('areas')
            ->when(
                $request->filled('governorate_id'),
                fn ($query) => $query->where(
                    'governorate_id',
                    $request->integer('governorate_id')
                )
            )
            ->orderBy('name_en')
            ->paginate(20);

        return DistrictResource::collection($districts);
    }

    public function store(StoreDistrictRequest $request): JsonResponse
    {
        $district = District::create($request->validated());

        return (new DistrictResource(
            $district->load('governorate')->loadCount('areas')
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(District $district): DistrictResource
    {
        Gate::authorize('view', $district);

        return new DistrictResource(
            $district->load('governorate')->loadCount('areas')
        );
    }

    public function update(
        UpdateDistrictRequest $request,
        District $district
    ): DistrictResource {
        $district->update($request->validated());

        return new DistrictResource(
            $district->refresh()
                ->load('governorate')
                ->loadCount('areas')
        );
    }

    public function destroy(District $district): Response
    {
        Gate::authorize('delete', $district);

        $district->delete();

        return response()->noContent();
    }
}
