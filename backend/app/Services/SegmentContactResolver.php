<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Builder;

class SegmentContactResolver
{
    /**
     * @return Builder<Contact>
     */
    public function query(Segment $segment): Builder
    {
        if ($segment->type === 'static') {
            return Contact::query()
                ->whereHas(
                    'segments',
                    fn (Builder $query) => $query->where(
                        'segments.id',
                        $segment->id
                    )
                );
        }

        $criteria = $segment->criteria ?? [];
        $query = Contact::query();

        if (isset($criteria['contact_status'])) {
            $query->where(
                'status',
                $criteria['contact_status']
            );
        }

        if (isset($criteria['area_id'])) {
            $query->where(
                'area_id',
                $criteria['area_id']
            );
        }

        if (isset($criteria['preferred_language'])) {
            $query->where(
                'preferred_language',
                $criteria['preferred_language']
            );
        }

        if (isset($criteria['preferred_channel'])) {
            $query->where(
                'preferred_channel',
                $criteria['preferred_channel']
            );
        }

        if (
            isset($criteria['consent_channel'])
            || isset($criteria['consent_status'])
        ) {
            $query->whereHas(
                'consents',
                function (Builder $consentQuery) use (
                    $criteria
                ): void {
                    if (isset($criteria['consent_channel'])) {
                        $consentQuery->where(
                            'channel',
                            $criteria['consent_channel']
                        );
                    }

                    if (isset($criteria['consent_status'])) {
                        $consentQuery->where(
                            'status',
                            $criteria['consent_status']
                        );
                    }
                }
            );
        }

        return $query;
    }
}
