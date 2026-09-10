<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Sharaf;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity' => ['nullable', 'string', 'max:64'],
            'auditable_id' => ['nullable', 'integer'],
            'parent_entity' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer'],
            'include_children' => ['nullable', 'boolean'],
            'sharaf_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:32'],
            'actor_its' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        return $this->jsonSuccessWithData($this->paginateLogs($request));
    }

    /**
     * GET /api/sharafs/{sharaf_id}/audit-logs
     */
    public function forSharaf(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make(
            array_merge($request->all(), ['sharaf_id' => $sharaf_id]),
            [
                'sharaf_id' => ['required', 'integer'],
                'action' => ['nullable', 'string', 'max:32'],
                'actor_its' => ['nullable', 'string', 'max:255'],
                'q' => ['nullable', 'string', 'max:255'],
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]
        );

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharaf = Sharaf::find((int) $sharaf_id);
        if (! $sharaf) {
            return $this->jsonError('NOT_FOUND', 'Sharaf not found.', 404);
        }

        $request->merge(['sharaf_id' => (int) $sharaf_id]);

        return $this->jsonSuccessWithData($this->paginateLogs($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function paginateLogs(Request $request): array
    {
        $query = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');

        $sharafId = $request->filled('sharaf_id') ? (int) $request->input('sharaf_id') : null;
        if ($sharafId !== null) {
            $query->where(function ($q) use ($sharafId) {
                $q->where(function ($inner) use ($sharafId) {
                    $inner->where('entity', 'sharaf')->where('auditable_id', $sharafId);
                })->orWhere(function ($inner) use ($sharafId) {
                    $inner->where('parent_entity', 'sharaf')->where('parent_id', $sharafId);
                });
            });
        } else {
            $entity = $request->input('entity');
            $auditableId = $request->filled('auditable_id') ? (int) $request->input('auditable_id') : null;
            $includeChildren = $request->boolean('include_children');

            if (is_string($entity) && $entity !== '') {
                if ($includeChildren) {
                    $query->where(function ($q) use ($entity, $auditableId) {
                        $q->where(function ($inner) use ($entity, $auditableId) {
                            $inner->where('entity', $entity);
                            if ($auditableId !== null) {
                                $inner->where('auditable_id', $auditableId);
                            }
                        })->orWhere(function ($inner) use ($entity, $auditableId) {
                            $inner->where('parent_entity', $entity);
                            if ($auditableId !== null) {
                                $inner->where('parent_id', $auditableId);
                            }
                        });
                    });
                } else {
                    $query->where('entity', $entity);
                    if ($auditableId !== null) {
                        $query->where('auditable_id', $auditableId);
                    }
                }
            } elseif ($auditableId !== null) {
                $query->where('auditable_id', $auditableId);
            }

            if ($request->filled('parent_entity')) {
                $query->where('parent_entity', $request->input('parent_entity'));
            }
            if ($request->filled('parent_id')) {
                $query->where('parent_id', (int) $request->input('parent_id'));
            }
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('actor_its')) {
            $query->where('actor_its', $request->input('actor_its'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->input('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('summary', 'like', $term)
                    ->orWhere('actor_its', 'like', $term)
                    ->orWhere('actor_name', 'like', $term);
            });
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from').' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to').' 23:59:59');
        }

        $perPage = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);
        $results = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($results->items())->map(function (AuditLog $log) {
            $arr = $log->toArray();
            $arr['entity_label'] = AuditLogService::ENTITY_LABELS[$log->entity] ?? $log->entity;
            $arr['parent_entity_label'] = $log->parent_entity
                ? (AuditLogService::ENTITY_LABELS[$log->parent_entity] ?? $log->parent_entity)
                : null;

            return $arr;
        })->values()->all();

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
            'entities' => AuditLogService::ENTITY_LABELS,
        ];
    }
}
