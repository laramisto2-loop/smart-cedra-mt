<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateCallScriptRequest;
use App\Http\Requests\StoreCallScriptRequest;
use App\Http\Requests\UpdateCallScriptRequest;
use App\Http\Resources\CallScriptResource;
use App\Models\CallScript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallScriptController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', CallScript::class);

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'language_code' => [
                'nullable',
                'string',
                'max:10',
            ],
            'status' => [
                'nullable',
                Rule::in(CallScript::STATUSES),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $scripts = CallScript::query()
            ->with('creator')
            ->withCount('queues')
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
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'description',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'body',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('language_code'),
                fn ($query) => $query->where(
                    'language_code',
                    $request->input('language_code')
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
            ->orderBy('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return CallScriptResource::collection($scripts);
    }

    public function store(
        StoreCallScriptRequest $request
    ): JsonResponse {
        $attributes = $request->validated();
        $attributes['created_by_user_id'] = $request->user()->id;
        $attributes['status'] = 'draft';

        $callScript = CallScript::query()->create($attributes);

        return (new CallScriptResource(
            $this->loadCallScript($callScript)
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        CallScript $callScript
    ): CallScriptResource {
        Gate::authorize('view', $callScript);

        return new CallScriptResource(
            $this->loadCallScript($callScript)
        );
    }

    public function update(
        UpdateCallScriptRequest $request,
        CallScript $callScript
    ): CallScriptResource {
        $callScript->update($request->validated());

        return new CallScriptResource(
            $this->loadCallScript($callScript->refresh())
        );
    }

    public function activate(
        ActivateCallScriptRequest $request,
        CallScript $callScript
    ): CallScriptResource {
        $callScript->update([
            'status' => $request->validated('status'),
        ]);

        return new CallScriptResource(
            $this->loadCallScript($callScript->refresh())
        );
    }

    public function destroy(
        CallScript $callScript
    ): Response {
        Gate::authorize('delete', $callScript);

        $callScript->delete();

        return response()->noContent();
    }

    private function loadCallScript(
        CallScript $callScript
    ): CallScript {
        return $callScript
            ->load([
                'creator',
                'queues.creator',
            ])
            ->loadCount('queues');
    }
}
