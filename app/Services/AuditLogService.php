<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Census;
use App\Models\Currency;
use App\Models\CurrencyConversion;
use App\Models\Event;
use App\Models\Miqaat;
use App\Models\MiqaatCheckDepartment;
use App\Models\PaymentDefinition;
use App\Models\PaymentDefinitionMapping;
use App\Models\Sharaf;
use App\Models\SharafClearance;
use App\Models\SharafDefinition;
use App\Models\SharafDefinitionMapping;
use App\Models\SharafMember;
use App\Models\SharafPayment;
use App\Models\SharafPosition;
use App\Models\SharafPositionMapping;
use App\Models\SharafType;
use App\Models\SilaFitraConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnitEnum;

class AuditLogService
{
    public const ENTITY_LABELS = [
        'miqaat' => 'Miqaat',
        'event' => 'Event',
        'sharaf_definition' => 'Sharaf Definition',
        'sharaf_position' => 'Sharaf Position',
        'payment_definition' => 'Payment Definition',
        'currency' => 'Currency',
        'currency_conversion' => 'Currency Conversion',
        'sharaf_type' => 'Sharaf Type',
        'user' => 'User',
        'miqaat_check_definition' => 'Check Definition',
        'sharaf_definition_mapping' => 'Sharaf Definition Mapping',
        'sharaf_position_mapping' => 'Position Mapping',
        'payment_definition_mapping' => 'Payment Definition Mapping',
        'sila_fitra_config' => 'Sila Fitra Config',
        'sharaf' => 'Sharaf',
        'sharaf_member' => 'Sharaf Member',
        'sharaf_payment' => 'Sharaf Payment',
        'sharaf_clearance' => 'Sharaf Clearance',
    ];

    private const CLASS_TO_ENTITY = [
        Miqaat::class => 'miqaat',
        Event::class => 'event',
        SharafDefinition::class => 'sharaf_definition',
        SharafPosition::class => 'sharaf_position',
        PaymentDefinition::class => 'payment_definition',
        Currency::class => 'currency',
        CurrencyConversion::class => 'currency_conversion',
        SharafType::class => 'sharaf_type',
        User::class => 'user',
        MiqaatCheckDepartment::class => 'miqaat_check_definition',
        SharafDefinitionMapping::class => 'sharaf_definition_mapping',
        SharafPositionMapping::class => 'sharaf_position_mapping',
        PaymentDefinitionMapping::class => 'payment_definition_mapping',
        SilaFitraConfig::class => 'sila_fitra_config',
        Sharaf::class => 'sharaf',
        SharafMember::class => 'sharaf_member',
        SharafPayment::class => 'sharaf_payment',
        SharafClearance::class => 'sharaf_clearance',
    ];

    private const REDACTED_FIELDS = ['password', 'remember_token'];

    public function recordCreated(Model $model): void
    {
        $entity = $this->entityFor($model);
        if ($entity === null) {
            return;
        }

        $newValues = $this->serializeAttributes($model, $model->getAttributes());
        $this->write(
            $entity,
            (int) $model->getKey(),
            'created',
            null,
            $newValues,
            $this->summary($entity, 'created', $model, $newValues, null),
            $this->parentFor($model)
        );
    }

    public function recordUpdated(Model $model): void
    {
        $entity = $this->entityFor($model);
        if ($entity === null) {
            return;
        }

        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }

        $oldValues = [];
        $newValues = [];
        foreach (array_keys($changes) as $key) {
            if (in_array($key, self::REDACTED_FIELDS, true)) {
                $oldValues[$key] = '[redacted]';
                $newValues[$key] = '[redacted]';
                continue;
            }
            $oldValues[$key] = $this->serializeValue($model->getOriginal($key));
            $newValues[$key] = $this->serializeValue($changes[$key]);
        }

        $this->write(
            $entity,
            (int) $model->getKey(),
            'updated',
            $oldValues,
            $newValues,
            $this->summary($entity, 'updated', $model, $newValues, $oldValues),
            $this->parentFor($model)
        );
    }

    public function recordDeleted(Model $model): void
    {
        $entity = $this->entityFor($model);
        if ($entity === null) {
            return;
        }

        $oldValues = $this->serializeAttributes($model, $model->getAttributes());
        $this->write(
            $entity,
            (int) $model->getKey(),
            'deleted',
            $oldValues,
            null,
            $this->summary($entity, 'deleted', $model, null, $oldValues),
            $this->parentFor($model)
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function recordManual(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        ?string $summary = null,
        array $metadata = []
    ): void {
        $entity = $this->entityFor($model);
        if ($entity === null) {
            return;
        }

        $this->write(
            $entity,
            (int) $model->getKey(),
            $action,
            $oldValues !== null ? $this->serializeAttributes($model, $oldValues) : null,
            $newValues !== null ? $this->serializeAttributes($model, $newValues) : null,
            $summary ?? $this->summary($entity, $action, $model, $newValues, $oldValues),
            $this->parentFor($model),
            $metadata
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array{entity: string, id: int}|null  $parent
     * @param  array<string, mixed>  $metadata
     */
    private function write(
        string $entity,
        int $auditableId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        string $summary,
        ?array $parent,
        array $metadata = []
    ): void {
        try {
            [$actorIts, $actorName] = $this->resolveActor();

            AuditLog::create([
                'entity' => $entity,
                'auditable_id' => $auditableId,
                'action' => $action,
                'actor_its' => $actorIts,
                'actor_name' => $actorName,
                'summary' => $summary,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => $metadata === [] ? null : $metadata,
                'parent_entity' => $parent['entity'] ?? null,
                'parent_id' => $parent['id'] ?? null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to write audit log', [
                'entity' => $entity,
                'auditable_id' => $auditableId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function entityFor(Model $model): ?string
    {
        return self::CLASS_TO_ENTITY[$model::class] ?? null;
    }

    /**
     * @return array{entity: string, id: int}|null
     */
    private function parentFor(Model $model): ?array
    {
        if ($model instanceof SharafMember || $model instanceof SharafPayment || $model instanceof SharafClearance) {
            $id = (int) $model->getAttribute('sharaf_id');

            return $id > 0 ? ['entity' => 'sharaf', 'id' => $id] : null;
        }

        if ($model instanceof SharafPosition || $model instanceof PaymentDefinition || $model instanceof Sharaf) {
            $id = (int) $model->getAttribute('sharaf_definition_id');

            return $id > 0 ? ['entity' => 'sharaf_definition', 'id' => $id] : null;
        }

        if ($model instanceof SharafDefinition || $model instanceof Event) {
            $id = (int) $model->getAttribute($model instanceof Event ? 'miqaat_id' : 'event_id');
            $entity = $model instanceof Event ? 'miqaat' : 'event';

            return $id > 0 ? ['entity' => $entity, 'id' => $id] : null;
        }

        if ($model instanceof SilaFitraConfig) {
            $id = (int) $model->getAttribute('miqaat_id');

            return $id > 0 ? ['entity' => 'miqaat', 'id' => $id] : null;
        }

        if ($model instanceof SharafPositionMapping || $model instanceof PaymentDefinitionMapping) {
            $id = (int) $model->getAttribute('sharaf_definition_mapping_id');

            return $id > 0 ? ['entity' => 'sharaf_definition_mapping', 'id' => $id] : null;
        }

        if ($model instanceof MiqaatCheckDepartment) {
            $id = (int) $model->getAttribute('miqaat_id');

            return $id > 0 ? ['entity' => 'miqaat', 'id' => $id] : null;
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveActor(): array
    {
        $its = null;
        try {
            $request = request();
            $user = $request?->user();
            if ($user instanceof User && filled($user->its_no)) {
                $its = (string) $user->its_no;
            } else {
                $cookie = $request?->cookie('user');
                if (is_string($cookie) && preg_match('/\A[0-9]+\z/', $cookie) === 1) {
                    $its = $cookie;
                }
            }
        } catch (Throwable) {
            $its = null;
        }

        if ($its === null) {
            return [null, null];
        }

        $name = User::where('its_no', $its)->value('name');
        if (! is_string($name) || $name === '') {
            $name = Census::where('its_id', $its)->value('name');
        }

        return [$its, is_string($name) && $name !== '' ? $name : null];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function serializeAttributes(Model $model, array $attributes): array
    {
        $out = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, self::REDACTED_FIELDS, true)) {
                $out[$key] = '[redacted]';
                continue;
            }
            $out[$key] = $this->serializeValue($value);
        }

        return $out;
    }

    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $oldValues
     */
    private function summary(
        string $entity,
        string $action,
        Model $model,
        ?array $newValues,
        ?array $oldValues
    ): string {
        $label = self::ENTITY_LABELS[$entity] ?? $entity;
        $name = $this->displayName($model, $newValues ?? $oldValues ?? []);

        if ($entity === 'sharaf_member') {
            $its = $model->getAttribute('its_id') ?? ($newValues['its_id'] ?? $oldValues['its_id'] ?? null);
            $memberName = $model->getAttribute('name') ?? ($newValues['name'] ?? $oldValues['name'] ?? null);
            $who = trim((string) ($memberName ? "{$memberName} ({$its})" : $its));
            $sharafId = $model->getAttribute('sharaf_id');

            return match ($action) {
                'created' => "Added member {$who} to sharaf #{$sharafId}",
                'deleted' => "Removed member {$who} from sharaf #{$sharafId}",
                default => "Updated member {$who} on sharaf #{$sharafId}: ".$this->changedFields($newValues),
            };
        }

        if ($entity === 'sharaf_payment') {
            $sharafId = $model->getAttribute('sharaf_id');

            return match ($action) {
                'created' => "Set payment on sharaf #{$sharafId}",
                'deleted' => "Removed payment on sharaf #{$sharafId}",
                default => "Updated payment on sharaf #{$sharafId}: ".$this->changedFields($newValues),
            };
        }

        $named = $name !== '' ? " \"{$name}\"" : '';

        return match ($action) {
            'created' => "Created {$label}{$named}",
            'deleted' => "Deleted {$label}{$named}",
            'copied' => "Copied {$label}{$named}",
            default => "Updated {$label}{$named}: ".$this->changedFields($newValues),
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function displayName(Model $model, array $values): string
    {
        foreach (['name', 'display_name', 'its_no', 'its_id', 'hof_its', 'token'] as $field) {
            $value = $model->getAttribute($field) ?? ($values[$field] ?? null);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $from = $model->getAttribute('from_currency') ?? ($values['from_currency'] ?? null);
        $to = $model->getAttribute('to_currency') ?? ($values['to_currency'] ?? null);
        if (is_string($from) && is_string($to) && $from !== '' && $to !== '') {
            return "{$from} → {$to}";
        }

        $id = $model->getKey();

        return $id !== null ? '#'.$id : '';
    }

    /**
     * @param  array<string, mixed>|null  $newValues
     */
    private function changedFields(?array $newValues): string
    {
        if ($newValues === null || $newValues === []) {
            return 'fields';
        }

        return implode(', ', array_keys($newValues));
    }
}
