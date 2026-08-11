<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactInteractionRequest;
use App\Http\Requests\UpdateContactInteractionRequest;
use App\Http\Resources\ContactInteractionResource;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\ContactInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactInteractionController extends Controller
{
    public function index(
        Request $request,
        Contact $contact
    ): AnonymousResourceCollection {
        Gate::authorize('view', $contact);
        Gate::authorize('viewAny', ContactInteraction::class);

        $request->validate([
            'channel' => [
                'nullable',
                'string',
                Rule::in(ContactInteraction::CHANNELS),
            ],
            'direction' => [
                'nullable',
                'string',
                Rule::in(ContactInteraction::DIRECTIONS),
            ],
            'outcome' => [
                'nullable',
                'string',
                Rule::in(ContactInteraction::OUTCOMES),
            ],
            'date_from' => [
                'nullable',
                'date',
            ],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $interactions = $contact->interactions()
            ->with('recorder')
            ->when(
                $request->filled('channel'),
                fn ($query) => $query->where(
                    'channel',
                    $request->input('channel')
                )
            )
            ->when(
                $request->filled('direction'),
                fn ($query) => $query->where(
                    'direction',
                    $request->input('direction')
                )
            )
            ->when(
                $request->filled('outcome'),
                fn ($query) => $query->where(
                    'outcome',
                    $request->input('outcome')
                )
            )
            ->when(
                $request->filled('date_from'),
                fn ($query) => $query->whereDate(
                    'occurred_at',
                    '>=',
                    $request->input('date_from')
                )
            )
            ->when(
                $request->filled('date_to'),
                fn ($query) => $query->whereDate(
                    'occurred_at',
                    '<=',
                    $request->input('date_to')
                )
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return ContactInteractionResource::collection(
            $interactions
        );
    }

    public function store(
        StoreContactInteractionRequest $request,
        Contact $contact
    ): JsonResponse {
        $attributes = $request->validated();
        $direction = $attributes['direction'] ?? 'outbound';

        $attributes['direction'] = $direction;
        $attributes['recorded_by_user_id'] = $request->user()->id;

        $attributes = array_merge(
            $attributes,
            $this->consentMetadata(
                $contact,
                $attributes['channel'],
                $direction
            )
        );

        $interaction = $contact->interactions()->create(
            $attributes
        );

        return (new ContactInteractionResource(
            $interaction->load('recorder')
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        ContactInteraction $contactInteraction
    ): ContactInteractionResource {
        Gate::authorize('view', $contactInteraction);

        return new ContactInteractionResource(
            $contactInteraction->load('recorder')
        );
    }

    public function update(
        UpdateContactInteractionRequest $request,
        ContactInteraction $contactInteraction
    ): ContactInteractionResource {
        $attributes = $request->validated();

        if (
            array_key_exists('channel', $attributes)
            || array_key_exists('direction', $attributes)
        ) {
            $channel = $attributes['channel']
                ?? $contactInteraction->channel;
            $direction = $attributes['direction']
                ?? $contactInteraction->direction;

            $attributes = array_merge(
                $attributes,
                $this->consentMetadata(
                    $contactInteraction->contact,
                    $channel,
                    $direction
                )
            );
        }

        $contactInteraction->update($attributes);

        return new ContactInteractionResource(
            $contactInteraction
                ->refresh()
                ->load('recorder')
        );
    }

    public function destroy(
        ContactInteraction $contactInteraction
    ): Response {
        Gate::authorize('delete', $contactInteraction);

        $contactInteraction->delete();

        return response()->noContent();
    }

    /**
     * @return array{
     *     consent_status_snapshot: string|null,
     *     consent_checked_at: Carbon|null
     * }
     */
    private function consentMetadata(
        Contact $contact,
        string $channel,
        string $direction
    ): array {
        if (! in_array(
            $channel,
            ContactConsent::CHANNELS,
            true
        )) {
            return [
                'consent_status_snapshot' => null,
                'consent_checked_at' => null,
            ];
        }

        $consent = $contact->consents()
            ->where('channel', $channel)
            ->first();

        $status = $consent?->status ?? 'unknown';

        if (
            $direction === 'outbound'
            && $status !== 'granted'
        ) {
            throw ValidationException::withMessages([
                'channel' => [
                    'Granted consent is required before recording an outbound interaction on this channel.',
                ],
            ]);
        }

        return [
            'consent_status_snapshot' => $status,
            'consent_checked_at' => now(),
        ];
    }
}
