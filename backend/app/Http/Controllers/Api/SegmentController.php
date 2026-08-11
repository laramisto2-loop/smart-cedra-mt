<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSegmentRequest;
use App\Http\Requests\SyncSegmentMembersRequest;
use App\Http\Requests\UpdateSegmentRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\SegmentResource;
use App\Models\ContactSegment;
use App\Models\Segment;
use App\Services\SegmentContactResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SegmentController extends Controller
{
    public function __construct(
        private readonly SegmentContactResolver $resolver
    ) {}

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Segment::class);

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'type' => [
                'nullable',
                'string',
                Rule::in(Segment::TYPES),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(Segment::STATUSES),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $segments = Segment::query()
            ->with('creator')
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
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'name',
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
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->input('status')
                )
            )
            ->orderBy('name')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        $segments->getCollection()
            ->each(function (Segment $segment): void {
                $segment->setAttribute(
                    'contacts_count',
                    $this->resolver
                        ->query($segment)
                        ->count()
                );
            });

        return SegmentResource::collection($segments);
    }

    public function store(
        StoreSegmentRequest $request
    ): JsonResponse {
        $attributes = $request->validated();
        $attributes['created_by_user_id'] = $request->user()->id;

        if (($attributes['type'] ?? 'static') === 'static') {
            $attributes['criteria'] = null;
        }

        $segment = Segment::create($attributes);

        return (new SegmentResource(
            $this->loadSegment($segment)
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Segment $segment): SegmentResource
    {
        Gate::authorize('view', $segment);

        return new SegmentResource(
            $this->loadSegment($segment)
        );
    }

    public function update(
        UpdateSegmentRequest $request,
        Segment $segment
    ): SegmentResource {
        $attributes = $request->validated();

        if ($attributes['type'] === 'static') {
            $attributes['criteria'] = null;
        }

        DB::transaction(function () use (
            $segment,
            $attributes
        ): void {
            if ($attributes['type'] === 'dynamic') {
                ContactSegment::query()
                    ->where('segment_id', $segment->id)
                    ->get()
                    ->each(
                        fn (ContactSegment $membership) => $membership
                            ->delete()
                    );
            }

            $segment->update($attributes);
        });

        return new SegmentResource(
            $this->loadSegment($segment->refresh())
        );
    }

    public function destroy(Segment $segment): Response
    {
        Gate::authorize('delete', $segment);

        DB::transaction(function () use ($segment): void {
            ContactSegment::query()
                ->where('segment_id', $segment->id)
                ->get()
                ->each(
                    fn (ContactSegment $membership) => $membership
                        ->delete()
                );

            $segment->delete();
        });

        return response()->noContent();
    }

    public function members(
        Request $request,
        Segment $segment
    ): AnonymousResourceCollection {
        Gate::authorize('view', $segment);

        $request->validate([
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $contacts = $this->resolver
            ->query($segment)
            ->with([
                'area.district.governorate',
                'creator',
                'consents.recorder',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    public function syncMembers(
        SyncSegmentMembersRequest $request,
        Segment $segment
    ): SegmentResource {
        $contactIds = collect(
            $request->validated('contact_ids')
        )
            ->map(fn ($contactId): int => (int) $contactId)
            ->values();

        DB::transaction(function () use (
            $request,
            $segment,
            $contactIds
        ): void {
            $currentIds = ContactSegment::query()
                ->where('segment_id', $segment->id)
                ->pluck('contact_id')
                ->map(fn ($contactId): int => (int) $contactId);

            $removedIds = $currentIds
                ->diff($contactIds)
                ->values();

            $addedIds = $contactIds
                ->diff($currentIds)
                ->values();

            if ($removedIds->isNotEmpty()) {
                ContactSegment::query()
                    ->where('segment_id', $segment->id)
                    ->whereIn('contact_id', $removedIds)
                    ->get()
                    ->each(
                        fn (ContactSegment $membership) => $membership
                            ->delete()
                    );
            }

            $addedIds->each(
                function (int $contactId) use (
                    $request,
                    $segment
                ): void {
                    ContactSegment::create([
                        'contact_id' => $contactId,
                        'segment_id' => $segment->id,
                        'added_by_user_id' => $request->user()->id,
                        'added_at' => now(),
                    ]);
                }
            );
        });

        return new SegmentResource(
            $this->loadSegment($segment->refresh())
        );
    }

    private function loadSegment(Segment $segment): Segment
    {
        $segment->load('creator');

        $segment->setAttribute(
            'contacts_count',
            $this->resolver
                ->query($segment)
                ->count()
        );

        return $segment;
    }
}
