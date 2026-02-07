<?php

namespace App\Services;

use App\Models\Census;
use App\Models\Event;
use App\Models\MiqaatCheck;
use App\Models\Sharaf;
use App\Models\SharafMember;
use App\Models\SharafPayment;
use App\Models\Wajebaat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingService
{
    /**
     * Get available entity types.
     */
    public function getAvailableEntities(): array
    {
        return [
            'census' => 'Census records',
            'wajebaat' => 'Wajebaat (Takhmeen) records',
            'sharafs' => 'Sharaf records',
            'sharaf-members' => 'Sharaf member records',
            'sharaf-payments' => 'Sharaf payment records',
            'events' => 'Event records',
            'miqaat-checks' => 'Miqaat check records',
        ];
    }

    /**
     * Get available fields for an entity type.
     */
    public function getAvailableFields(string $entityType): array
    {
        $fields = match ($entityType) {
            'census' => [
                'its_id', 'hof_id', 'father_its', 'mother_its', 'spouse_its', 'sabeel',
                'name', 'arabic_name', 'age', 'gender', 'misaq', 'marital_status',
                'blood_group', 'mobile', 'email', 'address', 'city', 'pincode',
                'mohalla', 'area', 'jamaat', 'jamiat', 'pwd', 'synced',
                'created_at', 'updated_at',
            ],
            'wajebaat' => [
                'id', 'miqaat_id', 'its_id', 'wg_id', 'amount', 'currency',
                'conversion_rate', 'status', 'wc_id', 'is_isolated',
                'created_at', 'updated_at',
            ],
            'sharafs' => [
                'id', 'sharaf_definition_id', 'rank', 'capacity', 'status',
                'hof_its', 'token', 'comments', 'created_at', 'updated_at',
            ],
            'sharaf-members' => [
                'id', 'sharaf_id', 'sharaf_position_id', 'its_id', 'sp_keyno',
                'name', 'phone', 'najwa', 'created_at', 'updated_at',
            ],
            'sharaf-payments' => [
                'id', 'sharaf_id', 'payment_definition_id', 'payment_amount',
                'payment_status', 'payment_currency', 'created_at', 'updated_at',
            ],
            'events' => [
                'id', 'miqaat_id', 'date', 'name', 'description',
                'created_at', 'updated_at',
            ],
            'miqaat-checks' => [
                'id', 'its_id', 'mcd_id', 'is_cleared', 'cleared_by_its',
                'cleared_at', 'notes', 'created_at', 'updated_at',
            ],
            default => [],
        };

        $relationships = match ($entityType) {
            'census' => ['hof', 'members'],
            'wajebaat' => ['miqaat', 'person', 'category', 'groupRows'],
            'sharafs' => ['sharafDefinition', 'sharafMembers', 'sharafClearances', 'sharafPayments', 'hof'],
            'sharaf-members' => ['sharaf', 'sharafPosition'],
            'sharaf-payments' => ['sharaf', 'paymentDefinition'],
            'events' => ['miqaat', 'sharafDefinitions'],
            'miqaat-checks' => ['miqaat', 'person', 'department'],
            default => [],
        };

        return [
            'fields' => $fields,
            'relationships' => $relationships,
        ];
    }

    /**
     * Get available filters for an entity type.
     */
    public function getAvailableFilters(string $entityType): array
    {
        return match ($entityType) {
            'census' => [
                ['name' => 'name', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'its_id', 'type' => 'string', 'operators' => ['equals']],
                ['name' => 'hof_id', 'type' => 'string', 'operators' => ['equals']],
                ['name' => 'city', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'jamaat', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'jamiat', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'mohalla', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'area', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'gender', 'type' => 'enum', 'values' => ['male', 'female'], 'operators' => ['equals']],
                ['name' => 'misaq', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'marital_status', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'age_min', 'type' => 'integer', 'operators' => ['gte']],
                ['name' => 'age_max', 'type' => 'integer', 'operators' => ['lte']],
                ['name' => 'date_from', 'type' => 'date', 'operators' => ['gte']],
                ['name' => 'date_to', 'type' => 'date', 'operators' => ['lte']],
            ],
            'wajebaat' => [
                ['name' => 'miqaat_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'its_id', 'type' => 'string', 'operators' => ['equals']],
                ['name' => 'wg_id', 'type' => 'integer', 'operators' => ['equals']],
                ['name' => 'status', 'type' => 'boolean', 'operators' => ['equals']],
                ['name' => 'amount_min', 'type' => 'decimal', 'operators' => ['gte']],
                ['name' => 'amount_max', 'type' => 'decimal', 'operators' => ['lte']],
                ['name' => 'currency', 'type' => 'string', 'operators' => ['equals', 'in']],
                ['name' => 'wc_id', 'type' => 'integer', 'operators' => ['equals']],
                ['name' => 'is_isolated', 'type' => 'boolean', 'operators' => ['equals']],
                ['name' => 'date_from', 'type' => 'date', 'operators' => ['gte']],
                ['name' => 'date_to', 'type' => 'date', 'operators' => ['lte']],
            ],
            'sharafs' => [
                ['name' => 'sharaf_definition_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'event_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'miqaat_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'status', 'type' => 'enum', 'values' => ['pending', 'bs_approved', 'confirmed', 'rejected', 'cancelled'], 'operators' => ['equals', 'in']],
                ['name' => 'hof_its', 'type' => 'string', 'operators' => ['equals']],
                ['name' => 'token', 'type' => 'string', 'operators' => ['like', 'equals']],
                ['name' => 'rank_min', 'type' => 'integer', 'operators' => ['gte']],
                ['name' => 'rank_max', 'type' => 'integer', 'operators' => ['lte']],
                ['name' => 'date_from', 'type' => 'date', 'operators' => ['gte']],
                ['name' => 'date_to', 'type' => 'date', 'operators' => ['lte']],
            ],
            'sharaf-members' => [
                ['name' => 'sharaf_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'sharaf_definition_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'its_id', 'type' => 'string', 'operators' => ['equals']],
                ['name' => 'sharaf_position_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'sp_keyno', 'type' => 'integer', 'operators' => ['equals']],
            ],
            'sharaf-payments' => [
                ['name' => 'sharaf_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'payment_definition_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'payment_status', 'type' => 'boolean', 'operators' => ['equals']],
                ['name' => 'payment_amount_min', 'type' => 'decimal', 'operators' => ['gte']],
                ['name' => 'payment_amount_max', 'type' => 'decimal', 'operators' => ['lte']],
            ],
            'events' => [
                ['name' => 'miqaat_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'date_from', 'type' => 'date', 'operators' => ['gte']],
                ['name' => 'date_to', 'type' => 'date', 'operators' => ['lte']],
            ],
            'miqaat-checks' => [
                ['name' => 'miqaat_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'its_id', 'type' => 'string', 'operators' => ['equals']],
                ['name' => 'mcd_id', 'type' => 'integer', 'operators' => ['equals', 'in']],
                ['name' => 'is_cleared', 'type' => 'boolean', 'operators' => ['equals']],
                ['name' => 'date_from', 'type' => 'date', 'operators' => ['gte']],
                ['name' => 'date_to', 'type' => 'date', 'operators' => ['lte']],
            ],
            default => [],
        };
    }

    /**
     * Build query for an entity type with filters.
     */
    public function buildQuery(string $entityType, array $filters): Builder
    {
        return match ($entityType) {
            'census' => $this->buildCensusQuery($filters),
            'wajebaat' => $this->buildWajebaatQuery($filters),
            'sharafs' => $this->buildSharafQuery($filters),
            'sharaf-members' => $this->buildSharafMemberQuery($filters),
            'sharaf-payments' => $this->buildSharafPaymentQuery($filters),
            'events' => $this->buildEventQuery($filters),
            'miqaat-checks' => $this->buildMiqaatCheckQuery($filters),
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Query data with filters and return collection.
     */
    public function query(string $entityType, array $filters, array $options = []): Collection
    {
        $query = $this->buildQuery($entityType, $filters);

        // Apply eager loading if specified
        if (isset($options['include']) && is_array($options['include'])) {
            $query->with($options['include']);
        }

        // Apply sorting
        if (isset($options['sort_by'])) {
            $sortOrder = $options['sort_order'] ?? 'asc';
            $query->orderBy($options['sort_by'], $sortOrder);
        }

        return $query->get();
    }

    /**
     * Export data to CSV with streaming.
     */
    public function exportToCsv(string $entityType, array $filters, array $fields = [], array $options = []): StreamedResponse
    {
        $filename = "{$entityType}_report_" . date('Y-m-d_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($entityType, $filters, $fields, $options) {
            $file = fopen('php://output', 'w');

            // Write UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Build query
            $query = $this->buildQuery($entityType, $filters);

            // Apply eager loading for relationships
            if (isset($options['include']) && is_array($options['include'])) {
                $query->with($options['include']);
            }

            // Apply sorting
            if (isset($options['sort_by'])) {
                $sortOrder = $options['sort_order'] ?? 'asc';
                $query->orderBy($options['sort_by'], $sortOrder);
            }

            // Get all results (for CSV, we don't paginate)
            $results = $query->get();

            // Determine fields to export
            if (empty($fields)) {
                $availableFields = $this->getAvailableFields($entityType);
                $fields = $availableFields['fields'] ?? [];
            }

            // Write header row
            fputcsv($file, $fields);

            // Write data rows
            foreach ($results as $row) {
                $csvRow = [];
                foreach ($fields as $field) {
                    $csvRow[] = $this->getFieldValue($row, $field);
                }
                fputcsv($file, $csvRow);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get field value from a model instance, handling relationships.
     */
    protected function getFieldValue($model, string $field): string
    {
        // Handle relationship fields (e.g., "event.name")
        if (str_contains($field, '.')) {
            $parts = explode('.', $field, 2);
            $relation = $parts[0];
            $relationField = $parts[1];

            if ($model->relationLoaded($relation) && $model->$relation) {
                return $this->getFieldValue($model->$relation, $relationField);
            }

            return '';
        }

        // Handle direct attributes
        if (isset($model->$field)) {
            $value = $model->$field;

            // Format dates
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            // Format booleans
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            // Handle null values
            if ($value === null) {
                return '';
            }

            return (string) $value;
        }

        return '';
    }

    /**
     * Build census query with filters.
     */
    protected function buildCensusQuery(array $filters): Builder
    {
        $query = Census::query();

        if (isset($filters['name'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['name'] . '%')
                  ->orWhere('arabic_name', 'like', '%' . $filters['name'] . '%');
            });
        }

        if (isset($filters['its_id'])) {
            $query->where('its_id', $filters['its_id']);
        }

        if (isset($filters['hof_id'])) {
            $query->where('hof_id', $filters['hof_id']);
        }

        if (isset($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }

        if (isset($filters['jamaat'])) {
            $query->where('jamaat', 'like', '%' . $filters['jamaat'] . '%');
        }

        if (isset($filters['jamiat'])) {
            $query->where('jamiat', 'like', '%' . $filters['jamiat'] . '%');
        }

        if (isset($filters['mohalla'])) {
            $query->where('mohalla', 'like', '%' . $filters['mohalla'] . '%');
        }

        if (isset($filters['area'])) {
            $query->where('area', 'like', '%' . $filters['area'] . '%');
        }

        if (isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['misaq'])) {
            $query->where('misaq', 'like', '%' . $filters['misaq'] . '%');
        }

        if (isset($filters['marital_status'])) {
            $query->where('marital_status', 'like', '%' . $filters['marital_status'] . '%');
        }

        if (isset($filters['age_min'])) {
            $query->where('age', '>=', (int) $filters['age_min']);
        }

        if (isset($filters['age_max'])) {
            $query->where('age', '<=', (int) $filters['age_max']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Build wajebaat query with filters.
     */
    protected function buildWajebaatQuery(array $filters): Builder
    {
        $query = Wajebaat::query();

        if (isset($filters['miqaat_id'])) {
            $miqaatIds = is_array($filters['miqaat_id']) ? $filters['miqaat_id'] : explode(',', $filters['miqaat_id']);
            $query->whereIn('miqaat_id', array_map('intval', $miqaatIds));
        }

        if (isset($filters['its_id'])) {
            $query->where('its_id', $filters['its_id']);
        }

        if (isset($filters['wg_id'])) {
            $query->where('wg_id', (int) $filters['wg_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['amount_min'])) {
            $query->where('amount', '>=', (float) $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $query->where('amount', '<=', (float) $filters['amount_max']);
        }

        if (isset($filters['currency'])) {
            $currencies = is_array($filters['currency']) ? $filters['currency'] : explode(',', $filters['currency']);
            $query->whereIn('currency', $currencies);
        }

        if (isset($filters['wc_id'])) {
            $query->where('wc_id', (int) $filters['wc_id']);
        }

        if (isset($filters['is_isolated'])) {
            $query->where('is_isolated', filter_var($filters['is_isolated'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Build sharaf query with filters.
     */
    protected function buildSharafQuery(array $filters): Builder
    {
        $query = Sharaf::query()
            ->whereHas('sharafDefinition.event.miqaat', fn ($q) => $q->active());

        if (isset($filters['sharaf_definition_id'])) {
            $definitionIds = is_array($filters['sharaf_definition_id']) 
                ? $filters['sharaf_definition_id'] 
                : explode(',', $filters['sharaf_definition_id']);
            $query->whereIn('sharaf_definition_id', array_map('intval', $definitionIds));
        }

        if (isset($filters['event_id'])) {
            $eventIds = is_array($filters['event_id']) ? $filters['event_id'] : explode(',', $filters['event_id']);
            $query->whereHas('sharafDefinition', function ($q) use ($eventIds) {
                $q->whereIn('event_id', array_map('intval', $eventIds));
            });
        }

        if (isset($filters['miqaat_id'])) {
            $miqaatIds = is_array($filters['miqaat_id']) ? $filters['miqaat_id'] : explode(',', $filters['miqaat_id']);
            $query->whereHas('sharafDefinition.event', function ($q) use ($miqaatIds) {
                $q->whereIn('miqaat_id', array_map('intval', $miqaatIds));
            });
        }

        if (isset($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : explode(',', $filters['status']);
            $query->whereIn('status', $statuses);
        }

        if (isset($filters['hof_its'])) {
            $query->where('hof_its', $filters['hof_its']);
        }

        if (isset($filters['token'])) {
            $query->where('token', 'like', '%' . $filters['token'] . '%');
        }

        if (isset($filters['rank_min'])) {
            $query->where('rank', '>=', (int) $filters['rank_min']);
        }

        if (isset($filters['rank_max'])) {
            $query->where('rank', '<=', (int) $filters['rank_max']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Build sharaf member query with filters.
     */
    protected function buildSharafMemberQuery(array $filters): Builder
    {
        $query = SharafMember::query();

        if (isset($filters['sharaf_id'])) {
            $sharafIds = is_array($filters['sharaf_id']) ? $filters['sharaf_id'] : explode(',', $filters['sharaf_id']);
            $query->whereIn('sharaf_id', array_map('intval', $sharafIds));
        }

        if (isset($filters['sharaf_definition_id'])) {
            $definitionIds = is_array($filters['sharaf_definition_id']) 
                ? $filters['sharaf_definition_id'] 
                : explode(',', $filters['sharaf_definition_id']);
            $query->whereHas('sharaf', function ($q) use ($definitionIds) {
                $q->whereIn('sharaf_definition_id', array_map('intval', $definitionIds));
            });
        }

        if (isset($filters['its_id'])) {
            $query->where('its_id', $filters['its_id']);
        }

        if (isset($filters['sharaf_position_id'])) {
            $positionIds = is_array($filters['sharaf_position_id']) 
                ? $filters['sharaf_position_id'] 
                : explode(',', $filters['sharaf_position_id']);
            $query->whereIn('sharaf_position_id', array_map('intval', $positionIds));
        }

        if (isset($filters['sp_keyno'])) {
            $query->where('sp_keyno', (int) $filters['sp_keyno']);
        }

        return $query;
    }

    /**
     * Build sharaf payment query with filters.
     */
    protected function buildSharafPaymentQuery(array $filters): Builder
    {
        $query = SharafPayment::query();

        if (isset($filters['sharaf_id'])) {
            $sharafIds = is_array($filters['sharaf_id']) ? $filters['sharaf_id'] : explode(',', $filters['sharaf_id']);
            $query->whereIn('sharaf_id', array_map('intval', $sharafIds));
        }

        if (isset($filters['payment_definition_id'])) {
            $definitionIds = is_array($filters['payment_definition_id']) 
                ? $filters['payment_definition_id'] 
                : explode(',', $filters['payment_definition_id']);
            $query->whereIn('payment_definition_id', array_map('intval', $definitionIds));
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', filter_var($filters['payment_status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['payment_amount_min'])) {
            $query->where('payment_amount', '>=', (float) $filters['payment_amount_min']);
        }

        if (isset($filters['payment_amount_max'])) {
            $query->where('payment_amount', '<=', (float) $filters['payment_amount_max']);
        }

        return $query;
    }

    /**
     * Build event query with filters.
     */
    protected function buildEventQuery(array $filters): Builder
    {
        $query = Event::query();

        if (isset($filters['miqaat_id'])) {
            $miqaatIds = is_array($filters['miqaat_id']) ? $filters['miqaat_id'] : explode(',', $filters['miqaat_id']);
            $query->whereIn('miqaat_id', array_map('intval', $miqaatIds));
        }

        if (isset($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Build miqaat check query with filters.
     */
    protected function buildMiqaatCheckQuery(array $filters): Builder
    {
        $query = MiqaatCheck::query();

        if (isset($filters['miqaat_id'])) {
            $miqaatIds = is_array($filters['miqaat_id']) ? $filters['miqaat_id'] : explode(',', $filters['miqaat_id']);
            $query->whereHas('department', function ($q) use ($miqaatIds) {
                $q->whereIn('miqaat_id', array_map('intval', $miqaatIds));
            });
        }

        if (isset($filters['its_id'])) {
            $query->where('its_id', $filters['its_id']);
        }

        if (isset($filters['mcd_id'])) {
            $mcdIds = is_array($filters['mcd_id']) ? $filters['mcd_id'] : explode(',', $filters['mcd_id']);
            $query->whereIn('mcd_id', array_map('intval', $mcdIds));
        }

        if (isset($filters['is_cleared'])) {
            $query->where('is_cleared', filter_var($filters['is_cleared'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
