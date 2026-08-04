<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'auditable_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->latest('id');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where(
                'auditable_type',
                $filters['auditable_type']
            );
        }

        if (! empty($filters['auditable_id'])) {
            $query->where(
                'auditable_id',
                $filters['auditable_id']
            );
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $filters['date_from']
            );
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $filters['date_to']
            );
        }

        $auditLogs = $query->paginate(
            $filters['per_page'] ?? 20
        )->withQueryString();

        return AuditLogResource::collection($auditLogs);
    }

    public function show(
        AuditLog $auditLog
    ): AuditLogResource {
        Gate::authorize('view', $auditLog);

        return new AuditLogResource(
            $auditLog->load('user:id,name,email')
        );
    }
}
