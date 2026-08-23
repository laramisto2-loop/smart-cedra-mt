<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOutboundMessageRequest;
use App\Http\Resources\MessageDeliveryEventResource;
use App\Http\Resources\OutboundMessageResource;
use App\Models\ContactConsent;
use App\Models\MessageDeliveryEvent;
use App\Models\OutboundMessage;
use App\Services\OutboundMessageService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OutboundMessageController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', OutboundMessage::class);

        $tenantId = app(TenantContext::class)->id();

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'channel' => [
                'nullable',
                Rule::in(OutboundMessage::CHANNELS),
            ],
            'source' => [
                'nullable',
                Rule::in(OutboundMessage::SOURCES),
            ],
            'status' => [
                'nullable',
                Rule::in(OutboundMessage::STATUSES),
            ],
            'consent_status' => [
                'nullable',
                Rule::in(ContactConsent::STATUSES),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'message_template_id' => [
                'nullable',
                'integer',
                Rule::exists('message_templates', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'sent_by_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where(
                        'tenant_id',
                        $tenantId
                    )
                ),
            ],
            'created_from' => [
                'nullable',
                'date',
            ],
            'created_to' => [
                'nullable',
                'date',
                'after_or_equal:created_from',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $messages = OutboundMessage::query()
            ->with([
                'contact',
                'template.creator',
                'consent',
                'sender',
            ])
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
                                    'reference_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'recipient',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'template_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'rendered_body',
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
                $request->filled('source'),
                fn ($query) => $query->where(
                    'source',
                    $request->input('source')
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->input('status')
                )
            )
            ->when(
                $request->filled('consent_status'),
                fn ($query) => $query->where(
                    'consent_status',
                    $request->input('consent_status')
                )
            )
            ->when(
                $request->filled('contact_id'),
                fn ($query) => $query->where(
                    'contact_id',
                    $request->integer('contact_id')
                )
            )
            ->when(
                $request->filled('message_template_id'),
                fn ($query) => $query->where(
                    'message_template_id',
                    $request->integer('message_template_id')
                )
            )
            ->when(
                $request->filled('sent_by_user_id'),
                fn ($query) => $query->where(
                    'sent_by_user_id',
                    $request->integer('sent_by_user_id')
                )
            )
            ->when(
                $request->filled('created_from'),
                fn ($query) => $query->where(
                    'created_at',
                    '>=',
                    $request->input('created_from')
                )
            )
            ->when(
                $request->filled('created_to'),
                fn ($query) => $query->where(
                    'created_at',
                    '<=',
                    $request->input('created_to')
                )
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return OutboundMessageResource::collection($messages);
    }

    public function store(
        StoreOutboundMessageRequest $request,
        OutboundMessageService $service
    ): JsonResponse {
        $message = $service->create(
            $request->user(),
            $request->validated()
        );

        $statusCode = $message->wasRecentlyCreated
            ? Response::HTTP_CREATED
            : Response::HTTP_OK;

        return (new OutboundMessageResource($message))
            ->response()
            ->setStatusCode($statusCode);
    }

    public function show(
        OutboundMessage $outboundMessage
    ): OutboundMessageResource {
        Gate::authorize('view', $outboundMessage);

        return new OutboundMessageResource(
            $this->loadMessage($outboundMessage)
        );
    }

    public function deliveryEvents(
        OutboundMessage $outboundMessage
    ): AnonymousResourceCollection {
        Gate::authorize('view', $outboundMessage);
        Gate::authorize(
            'viewAny',
            MessageDeliveryEvent::class
        );

        $events = $outboundMessage
            ->deliveryEvents()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return MessageDeliveryEventResource::collection($events);
    }

    private function loadMessage(
        OutboundMessage $outboundMessage
    ): OutboundMessage {
        return $outboundMessage->load([
            'contact',
            'template.creator',
            'consent',
            'sender',
            'deliveryEvents' => fn ($query) => $query
                ->orderBy('occurred_at')
                ->orderBy('id'),
        ]);
    }
}
