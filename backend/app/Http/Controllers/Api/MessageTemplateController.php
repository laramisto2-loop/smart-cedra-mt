<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveMessageTemplateRequest;
use App\Http\Requests\StoreMessageTemplateRequest;
use App\Http\Requests\UpdateMessageTemplateRequest;
use App\Http\Resources\MessageTemplateResource;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MessageTemplateController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', MessageTemplate::class);

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'channel' => [
                'nullable',
                Rule::in(MessageTemplate::CHANNELS),
            ],
            'category' => [
                'nullable',
                Rule::in(MessageTemplate::CATEGORIES),
            ],
            'status' => [
                'nullable',
                Rule::in(MessageTemplate::STATUSES),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $templates = MessageTemplate::query()
            ->with('creator')
            ->withCount('outboundMessages')
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
                                    'body',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('channel'),
                fn ($query) => $query->where(
                    'channel',
                    $request->input('channel')
                )
            )
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where(
                    'category',
                    $request->input('category')
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

        return MessageTemplateResource::collection($templates);
    }

    public function store(
        StoreMessageTemplateRequest $request
    ): JsonResponse {
        $attributes = $request->validated();
        $attributes['created_by_user_id'] = $request->user()->id;
        $attributes['status'] = 'draft';

        $template = MessageTemplate::query()->create($attributes);

        return (new MessageTemplateResource(
            $this->loadTemplate($template)
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        MessageTemplate $messageTemplate
    ): MessageTemplateResource {
        Gate::authorize('view', $messageTemplate);

        return new MessageTemplateResource(
            $this->loadTemplate($messageTemplate)
        );
    }

    public function update(
        UpdateMessageTemplateRequest $request,
        MessageTemplate $messageTemplate
    ): MessageTemplateResource {
        $messageTemplate->update($request->validated());

        return new MessageTemplateResource(
            $this->loadTemplate($messageTemplate->refresh())
        );
    }

    public function approve(
        ApproveMessageTemplateRequest $request,
        MessageTemplate $messageTemplate
    ): MessageTemplateResource {
        $messageTemplate->update([
            'status' => $request->validated('status'),
        ]);

        return new MessageTemplateResource(
            $this->loadTemplate($messageTemplate->refresh())
        );
    }

    public function destroy(
        MessageTemplate $messageTemplate
    ): Response {
        Gate::authorize('delete', $messageTemplate);

        $messageTemplate->delete();

        return response()->noContent();
    }

    private function loadTemplate(
        MessageTemplate $messageTemplate
    ): MessageTemplate {
        return $messageTemplate
            ->load('creator')
            ->loadCount('outboundMessages');
    }
}
