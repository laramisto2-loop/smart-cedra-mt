<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateElectionContestRequest;
use App\Http\Requests\StoreElectionContestRequest;
use App\Http\Requests\UpdateElectionContestRequest;
use App\Http\Resources\ElectionContestResource;
use App\Models\ElectionContest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ElectionContestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ElectionContest::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ElectionContest::STATUSES)],
            'election_date_from' => ['nullable', 'date'],
            'election_date_to' => ['nullable', 'date', 'after_or_equal:election_date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $contests = ElectionContest::query()
            ->with(['creator', 'activator', 'options' => fn ($query) => $query
                ->orderBy('ballot_order')
                ->orderBy('id')])
            ->withCount(['options', 'tallySheets'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->filled('election_date_from'),
                fn ($query) => $query->whereDate(
                    'election_date',
                    '>=',
                    $request->input('election_date_from')
                )
            )
            ->when(
                $request->filled('election_date_to'),
                fn ($query) => $query->whereDate(
                    'election_date',
                    '<=',
                    $request->input('election_date_to')
                )
            )
            ->orderByDesc('election_date')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return ElectionContestResource::collection($contests);
    }

    public function store(StoreElectionContestRequest $request): JsonResponse
    {
        $contest = DB::transaction(function () use ($request): ElectionContest {
            $attributes = Arr::except($request->validated(), 'options');
            $attributes['created_by_user_id'] = $request->user()->id;
            $attributes['status'] = ElectionContest::STATUS_DRAFT;

            $contest = ElectionContest::query()->create($attributes);

            foreach ($request->validated('options') as $option) {
                $contest->options()->create($option);
            }

            return $contest;
        });

        return (new ElectionContestResource($this->loadContest($contest)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ElectionContest $electionContest): ElectionContestResource
    {
        Gate::authorize('view', $electionContest);

        return new ElectionContestResource($this->loadContest($electionContest));
    }

    public function update(
        UpdateElectionContestRequest $request,
        ElectionContest $electionContest
    ): ElectionContestResource {
        DB::transaction(function () use ($request, $electionContest): void {
            $validated = $request->validated();
            $electionContest->update(Arr::except($validated, 'options'));

            if (! array_key_exists('options', $validated)) {
                return;
            }

            $retainedIds = [];

            foreach ($validated['options'] as $optionAttributes) {
                $optionId = Arr::pull($optionAttributes, 'id');

                if ($optionId === null) {
                    $option = $electionContest->options()->create($optionAttributes);
                } else {
                    $option = $electionContest->options()->findOrFail($optionId);
                    $option->update($optionAttributes);
                }

                $retainedIds[] = $option->id;
            }

            $electionContest->options()
                ->whereNotIn('id', $retainedIds)
                ->get()
                ->each
                ->delete();
        });

        return new ElectionContestResource(
            $this->loadContest($electionContest->refresh())
        );
    }

    public function activate(
        ActivateElectionContestRequest $request,
        ElectionContest $electionContest
    ): ElectionContestResource {
        if (! $electionContest->options()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'options' => 'At least one active election option is required before activation.',
            ]);
        }

        $electionContest->update([
            'status' => ElectionContest::STATUS_ACTIVE,
            'activated_by_user_id' => $request->user()->id,
            'activated_at' => now(),
        ]);

        return new ElectionContestResource(
            $this->loadContest($electionContest->refresh())
        );
    }

    public function close(ElectionContest $electionContest): ElectionContestResource
    {
        Gate::authorize('close', $electionContest);

        $electionContest->update([
            'status' => ElectionContest::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return new ElectionContestResource(
            $this->loadContest($electionContest->refresh())
        );
    }

    public function destroy(ElectionContest $electionContest): Response
    {
        Gate::authorize('delete', $electionContest);

        DB::transaction(function () use ($electionContest): void {
            $electionContest->options()->get()->each->delete();
            $electionContest->delete();
        });

        return response()->noContent();
    }

    private function loadContest(ElectionContest $contest): ElectionContest
    {
        return $contest
            ->load([
                'creator',
                'activator',
                'options' => fn ($query) => $query
                    ->orderBy('ballot_order')
                    ->orderBy('id'),
            ])
            ->loadCount(['options', 'tallySheets']);
    }
}
