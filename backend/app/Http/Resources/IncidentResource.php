<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'reference_code' => $this->reference_code,
            'campaign_task_id' => $this->campaign_task_id,
            'area_id' => $this->area_id,
            'polling_center_id' => $this->polling_center_id,
            'polling_station_id' => $this->polling_station_id,
            'reported_by_user_id' => $this->reported_by_user_id,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'location_notes' => $this->location_notes,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'reported_at' => $this->reported_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolution_notes' => $this->resolution_notes,
            'client_updated_at' => $this->client_updated_at?->toISOString(),
            'sync_version' => $this->sync_version,
            'is_terminal' => in_array(
                $this->status,
                ['resolved', 'dismissed'],
                true
            ),
            'reporter' => $this->whenLoaded(
                'reporter',
                function (): ?array {
                    if ($this->reporter === null) {
                        return null;
                    }

                    return [
                        'id' => $this->reporter->id,
                        'name' => $this->reporter->name,
                        'email' => $this->reporter->email,
                    ];
                }
            ),
            'assignee' => $this->whenLoaded(
                'assignee',
                function (): ?array {
                    if ($this->assignee === null) {
                        return null;
                    }

                    return [
                        'id' => $this->assignee->id,
                        'name' => $this->assignee->name,
                        'email' => $this->assignee->email,
                    ];
                }
            ),
            'reviewer' => $this->whenLoaded(
                'reviewer',
                function (): ?array {
                    if ($this->reviewer === null) {
                        return null;
                    }

                    return [
                        'id' => $this->reviewer->id,
                        'name' => $this->reviewer->name,
                    ];
                }
            ),
            'campaign_task' => $this->whenLoaded(
                'campaignTask',
                function (): ?array {
                    if ($this->campaignTask === null) {
                        return null;
                    }

                    return [
                        'id' => $this->campaignTask->id,
                        'title' => $this->campaignTask->title,
                        'status' => $this->campaignTask->status,
                    ];
                }
            ),
            'area' => new AreaResource(
                $this->whenLoaded('area')
            ),
            'polling_center' => new PollingCenterResource(
                $this->whenLoaded('pollingCenter')
            ),
            'polling_station' => new PollingStationResource(
                $this->whenLoaded('pollingStation')
            ),
            'attachments_count' => $this->whenCounted('attachments'),
            'attachments' => IncidentAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
            'actions' => [
                'update' => $request->user()?->can(
                    'update',
                    $this->resource
                ) ?? false,
                'assign' => $request->user()?->can(
                    'assign',
                    $this->resource
                ) ?? false,
                'review' => $request->user()?->can(
                    'review',
                    $this->resource
                ) ?? false,
                'manage_attachments' => $request->user()?->can(
                    'manageAttachments',
                    $this->resource
                ) ?? false,
                'delete' => $request->user()?->can(
                    'delete',
                    $this->resource
                ) ?? false,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
