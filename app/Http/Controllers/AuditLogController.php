<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Sharaf;
use App\Models\SharafClearance;
use App\Models\SharafMember;
use App\Models\User;
use App\Services\AuditLogPresenter;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'entity' => ['nullable', 'string', 'max:64'],
            'auditable_id' => ['nullable', 'integer'],
            'parent_entity' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer'],
            'include_children' => ['nullable', 'boolean'],
            'sharaf_id' => ['nullable', 'integer'],
        ], $this->sharedFilterRules()));

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
            array_merge([
                'sharaf_id' => ['required', 'integer'],
            ], $this->sharedFilterRules())
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
        if ($request->filled('subject_its')) {
            $this->applySubjectIts($query, (string) $request->input('subject_its'));
        }

        if ($request->boolean('all')) {
            $cap = 5000;
            $total = (clone $query)->count();
            $models = $query->limit($cap)->get();
            $count = $models->count();

            return [
                'data' => $this->presentLogs($models),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $count,
                    'total' => $total,
                    'last_page' => 1,
                    'from' => $count > 0 ? 1 : null,
                    'to' => $count > 0 ? $count : null,
                ],
                'entities' => AuditLogService::ENTITY_LABELS,
            ];
        }

        $perPage = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);
        $results = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $this->presentLogs(collect($results->items())),
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

    /**
     * @return array<string, array<int, string>>
     */
    private function sharedFilterRules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:32'],
            'actor_its' => ['nullable', 'string', 'max:255'],
            'subject_its' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'all' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Match the person the change is about: sharaf HOF, member, clearance, or user.
     * Live rows cover later edits that do not repeat the ITS. JSON and summary cover deleted rows.
     *
     * @param  Builder<AuditLog>  $query
     */
    private function applySubjectIts(Builder $query, string $its): void
    {
        $its = str_replace(['%', '_'], '', trim($its));
        if ($its === '') {
            return;
        }

        $sharafIds = Sharaf::query()->where('hof_its', $its)->pluck('id');
        $memberIds = SharafMember::query()->where('its_id', $its)->pluck('id');
        $clearanceIds = SharafClearance::query()->where('hof_its', $its)->pluck('id');
        $userIds = User::query()->where('its_no', $its)->pluck('id');
        $like = '%'.$its.'%';

        $query->where(function (Builder $outer) use ($its, $like, $sharafIds, $memberIds, $clearanceIds, $userIds) {
            $outer->where('summary', 'like', $like);

            foreach (['hof_its', 'its_id', 'its_no', 'master_its'] as $field) {
                $outer->orWhere('old_values->'.$field, $its)
                    ->orWhere('new_values->'.$field, $its);
            }

            $this->orWhereEntityIds($outer, 'sharaf', $sharafIds);
            if ($sharafIds->isNotEmpty()) {
                $outer->orWhere(function (Builder $inner) use ($sharafIds) {
                    $inner->where('parent_entity', 'sharaf')->whereIn('parent_id', $sharafIds);
                });
            }
            $this->orWhereEntityIds($outer, 'sharaf_member', $memberIds);
            $this->orWhereEntityIds($outer, 'sharaf_clearance', $clearanceIds);
            $this->orWhereEntityIds($outer, 'user', $userIds);
        });
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  Collection<int, mixed>  $ids
     */
    private function orWhereEntityIds(Builder $query, string $entity, Collection $ids): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        $query->orWhere(function (Builder $inner) use ($entity, $ids) {
            $inner->where('entity', $entity)->whereIn('auditable_id', $ids);
        });
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    private function presentLogs(Collection $logs): array
    {
        return app(AuditLogPresenter::class)->present($logs);
    }
}
