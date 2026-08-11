<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordContactConsentRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Contact::class);

        $tenantId = app(TenantContext::class)->id();

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'tenant_id',
                            $tenantId
                        )
                    ),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'archived',
                ]),
            ],
            'preferred_language' => [
                'nullable',
                'string',
                Rule::in([
                    'en',
                    'ar',
                ]),
            ],
            'preferred_channel' => [
                'nullable',
                'string',
                Rule::in(ContactConsent::CHANNELS),
            ],
            'consent_channel' => [
                'nullable',
                'string',
                Rule::in(ContactConsent::CHANNELS),
            ],
            'consent_status' => [
                'nullable',
                'string',
                Rule::in(ContactConsent::STATUSES),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $contacts = Contact::query()
            ->with([
                'area.district.governorate',
                'creator',
                'consents.recorder',
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
                                    'first_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'name_ar',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('area_id'),
                fn ($query) => $query->where(
                    'area_id',
                    $request->integer('area_id')
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
                $request->filled('preferred_language'),
                fn ($query) => $query->where(
                    'preferred_language',
                    $request->input('preferred_language')
                )
            )
            ->when(
                $request->filled('preferred_channel'),
                fn ($query) => $query->where(
                    'preferred_channel',
                    $request->input('preferred_channel')
                )
            )
            ->when(
                $request->filled('consent_channel')
                    || $request->filled('consent_status'),
                function ($query) use ($request): void {
                    $query->whereHas(
                        'consents',
                        function ($consentQuery) use ($request): void {
                            if ($request->filled('consent_channel')) {
                                $consentQuery->where(
                                    'channel',
                                    $request->input(
                                        'consent_channel'
                                    )
                                );
                            }

                            if ($request->filled('consent_status')) {
                                $consentQuery->where(
                                    'status',
                                    $request->input(
                                        'consent_status'
                                    )
                                );
                            }
                        }
                    );
                }
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(
                $request->integer('per_page', 20)
            )
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    public function store(
        StoreContactRequest $request
    ): JsonResponse {
        $attributes = $request->validated();
        $attributes['created_by_user_id'] = $request->user()->id;

        $contact = Contact::create($attributes);

        return (new ContactResource(
            $this->loadContact($contact)
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Contact $contact): ContactResource
    {
        Gate::authorize('view', $contact);

        return new ContactResource(
            $this->loadContact($contact)
        );
    }

    public function update(
        UpdateContactRequest $request,
        Contact $contact
    ): ContactResource {
        $contact->update($request->validated());

        return new ContactResource(
            $this->loadContact($contact->refresh())
        );
    }

    public function destroy(Contact $contact): Response
    {
        Gate::authorize('delete', $contact);

        DB::transaction(function () use ($contact): void {
            $contact->consents()
                ->get()
                ->each(
                    fn (ContactConsent $consent) => $consent->delete()
                );

            $contact->delete();
        });

        return response()->noContent();
    }

    public function recordConsent(
        RecordContactConsentRequest $request,
        Contact $contact
    ): ContactResource {
        $attributes = $request->validated();

        $consent = $contact->consents()
            ->firstOrNew([
                'channel' => $attributes['channel'],
            ]);

        $previousStatus = $consent->status;

        $consent->fill($attributes);
        $consent->recorded_by_user_id = $request->user()->id;

        if ($attributes['status'] === 'granted') {
            if (
                $previousStatus !== 'granted'
                || $consent->consented_at === null
            ) {
                $consent->consented_at = now();
            }

            $consent->revoked_at = null;
        } elseif ($attributes['status'] === 'revoked') {
            if (
                $previousStatus !== 'revoked'
                || $consent->revoked_at === null
            ) {
                $consent->revoked_at = now();
            }
        } else {
            $consent->consented_at = null;
            $consent->revoked_at = null;
        }

        $consent->save();

        return new ContactResource(
            $this->loadContact($contact->refresh())
        );
    }

    private function loadContact(Contact $contact): Contact
    {
        return $contact->load([
            'area.district.governorate',
            'creator',
            'consents.recorder',
        ]);
    }
}
