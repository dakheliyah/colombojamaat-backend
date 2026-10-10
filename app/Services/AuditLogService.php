<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Census;
use App\Models\Currency;
use App\Models\CurrencyConversion;
use App\Models\Event;
use App\Models\EventColorLegend;
use App\Models\Miqaat;
use App\Models\MiqaatCheck;
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
use App\Models\SilaFitraCalculation;
use App\Models\SilaFitraConfig;
use App\Models\User;
use App\Models\Wajebaat;
use App\Models\WajebaatGroup;
use Illuminate\Database\Eloquent\Builder;
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
        'event_color_legend' => 'Color Legend',
        'sila_fitra_calculation' => 'Sila Fitra',
        'miqaat_check' => 'Miqaat Check',
        'wajebaat' => 'Wajebaat',
        'wajebaat_group' => 'Wajebaat Group',
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
        EventColorLegend::class => 'event_color_legend',
        SilaFitraCalculation::class => 'sila_fitra_calculation',
        MiqaatCheck::class => 'miqaat_check',
        Wajebaat::class => 'wajebaat',
        WajebaatGroup::class => 'wajebaat_group',
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
        array $metadata = [],
        ?string $actorIts = null,
        ?string $actorName = null,
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
            $metadata,
            $actorIts,
            $actorName,
        );
    }

    /**
     * Apply an update row by row so Eloquent events write an audit entry.
     * A query-builder update() does not fire those events.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributes
     */
    public function updateEach(Builder $query, array $attributes): int
    {
        $updated = 0;
        foreach ($query->get() as $model) {
            $model->fill($attributes);
            if (! $model->isDirty()) {
                continue;
            }
            $model->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * Delete row by row so Eloquent events write an audit entry.
     *
     * @param  Builder<Model>  $query
     */
    public function deleteEach(Builder $query): int
    {
        $deleted = 0;
        foreach ($query->get() as $model) {
            $model->delete();
            $deleted++;
        }

        return $deleted;
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
        array $metadata = [],
        ?string $actorIts = null,
        ?string $actorName = null,
    ): void {
        try {
            if ($actorIts === null) {
                [$actorIts, $actorName] = $this->resolveActor();
            }

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

        if ($model instanceof MiqaatCheckDepartment || $model instanceof SilaFitraCalculation
            || $model instanceof Wajebaat || $model instanceof WajebaatGroup) {
            $id = (int) $model->getAttribute('miqaat_id');

            return $id > 0 ? ['entity' => 'miqaat', 'id' => $id] : null;
        }

        if ($model instanceof EventColorLegend) {
            $id = (int) $model->getAttribute('event_id');

            return $id > 0 ? ['entity' => 'event', 'id' => $id] : null;
        }

        if ($model instanceof MiqaatCheck) {
            $miqaatId = (int) MiqaatCheckDepartment::query()
                ->where('mcd_id', $model->getAttribute('mcd_id'))
                ->value('miqaat_id');

            return $miqaatId > 0 ? ['entity' => 'miqaat', 'id' => $miqaatId] : null;
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

        if ($entity === 'wajebaat') {
            $its = $model->getAttribute('its_id') ?? ($newValues['its_id'] ?? $oldValues['its_id'] ?? '');

            return match ($action) {
                'created' => "Saved wajebaat for {$its}",
                'deleted' => "Deleted wajebaat for {$its}",
                default => "Updated wajebaat for {$its}: ".$this->changedFields($newValues),
            };
        }

        if ($entity === 'wajebaat_group') {
            $its = $model->getAttribute('its_id') ?? ($newValues['its_id'] ?? $oldValues['its_id'] ?? '');
            $wgId = $model->getAttribute('wg_id') ?? ($newValues['wg_id'] ?? $oldValues['wg_id'] ?? '');

            return match ($action) {
                'created' => "Added {$its} to wajebaat group #{$wgId}",
                'deleted' => "Removed {$its} from wajebaat group #{$wgId}",
                default => "Updated wajebaat group #{$wgId} for {$its}: ".$this->changedFields($newValues),
            };
        }

        if ($entity === 'miqaat_check') {
            $its = $model->getAttribute('its_id') ?? ($newValues['its_id'] ?? $oldValues['its_id'] ?? '');
            if ($action === 'deleted') {
                return "Deleted miqaat check for {$its}";
            }
            if (is_array($newValues) && array_key_exists('is_cleared', $newValues)) {
                $state = filter_var($newValues['is_cleared'], FILTER_VALIDATE_BOOLEAN) ? 'cleared' : 'not cleared';

                return "Marked miqaat check for {$its} as {$state}";
            }

            return "Updated miqaat check for {$its}: ".$this->changedFields($newValues);
        }

        if ($entity === 'sila_fitra_calculation') {
            $hof = $model->getAttribute('hof_its') ?? ($newValues['hof_its'] ?? $oldValues['hof_its'] ?? '');
            if ($action === 'created') {
                return "Saved sila fitra for household {$hof}";
            }
            if ($action === 'deleted') {
                return "Deleted sila fitra for household {$hof}";
            }
            if (is_array($newValues) && array_key_exists('receipt_path', $newValues)
                && array_diff(array_keys($newValues), ['receipt_path']) === []) {
                return "Uploaded sila fitra receipt for household {$hof}";
            }
            if (is_array($newValues) && array_key_exists('payment_verified', $newValues)) {
                $verified = filter_var($newValues['payment_verified'], FILTER_VALIDATE_BOOLEAN);

                return $verified
                    ? "Verified sila fitra payment for household {$hof}"
                    : "Cleared sila fitra verification for household {$hof}";
            }

            return "Updated sila fitra for household {$hof}: ".$this->changedFields($newValues);
        }

        $named = $name !== '' ? " \"{$name}\"" : '';

        return match ($action) {
            'created' => "Created {$label}{$named}",
            'deleted' => "Deleted {$label}{$named}",
            'copied' => "Copied {$label}{$named}",
            'login' => "Logged in{$named}",
            'logout' => "Logged out{$named}",
            default => "Updated {$label}{$named}: ".$this->changedFields($newValues),
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function displayName(Model $model, array $values): string
    {
        foreach (['name', 'label', 'display_name', 'its_no', 'its_id', 'hof_its', 'token'] as $field) {
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
