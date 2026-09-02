<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGovernorateRequest;
use App\Http\Requests\UpdateGovernorateRequest;
use App\Http\Resources\GovernorateResource;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GovernorateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Governorate::class);

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $governorates = Governorate::query()
            ->withCount('districts')
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
            ->orderBy('name_en')
            ->paginate(20);

        return GovernorateResource::collection($governorates);
    }

    public function store(
        StoreGovernorateRequest $request
    ): JsonResponse {
        $governorate = Governorate::create($request->validated());

        return (new GovernorateResource(
            $governorate->loadCount('districts')
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        Governorate $governorate
    ): GovernorateResource {
        Gate::authorize('view', $governorate);

        return new GovernorateResource(
            $governorate->loadCount('districts')
        );
    }

    public function update(
        UpdateGovernorateRequest $request,
        Governorate $governorate
    ): GovernorateResource {
        $governorate->update($request->validated());

        return new GovernorateResource(
            $governorate->refresh()->loadCount('districts')
        );
    }

    public function destroy(
        Governorate $governorate
    ): Response {
        Gate::authorize('delete', $governorate);

        $governorate->delete();

        return response()->noContent();
    }
}

// What this does:
// Lists only the active tenant’s governorates.
// Returns 20 records per page.
// Uses policy authorization for viewing and deleting.
// Uses the Form Requests for authorized creation and updates.
// Returns HTTP 201 after creation and 204 after deletion.
// Does not expose tenant_id in API responses.
