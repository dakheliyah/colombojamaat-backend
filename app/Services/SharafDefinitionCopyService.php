<?php

namespace App\Services;

use App\Models\Event;
use App\Models\PaymentDefinition;
use App\Models\SharafDefinition;
use App\Models\SharafPosition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SharafDefinitionCopyService
{
    /**
     * Copy a sharaf definition (including positions and payment definitions) onto a target event.
     * Allocated sharafs, members, payments, and mappings are not copied.
     *
     * @param  array{name?: string|null, key?: string|null, description?: string|null, sharaf_type_id?: int|null}  $overrides
     */
    public function copy(int $sourceId, int $targetEventId, array $overrides = []): SharafDefinition
    {
        $source = SharafDefinition::with([
            'sharafPositions' => fn ($q) => $q->orderBy('order'),
            'paymentDefinitions' => fn ($q) => $q->orderBy('name'),
        ])->find($sourceId);
        if (! $source) {
            throw new InvalidArgumentException('Sharaf definition not found.');
        }

        $event = Event::find($targetEventId);
        if (! $event) {
            throw new InvalidArgumentException('Target event not found.');
        }

        $name = array_key_exists('name', $overrides) && $overrides['name'] !== null && $overrides['name'] !== ''
            ? $overrides['name']
            : $source->name;

        return DB::transaction(function () use ($source, $targetEventId, $name, $overrides) {
            $this->assertNameAvailable($targetEventId, $name);

            $copy = SharafDefinition::create([
                'event_id' => $targetEventId,
                'sharaf_type_id' => array_key_exists('sharaf_type_id', $overrides)
                    ? $overrides['sharaf_type_id']
                    : $source->sharaf_type_id,
                'name' => $name,
                'key' => array_key_exists('key', $overrides) ? $overrides['key'] : $source->key,
                'description' => array_key_exists('description', $overrides)
                    ? $overrides['description']
                    : $source->description,
            ]);

            $this->cloneChildren($source, $copy);

            app(AuditLogService::class)->recordManual(
                $copy,
                'copied',
                null,
                [
                    'source_sharaf_definition_id' => $source->id,
                    'source_name' => $source->name,
                    'target_event_id' => $targetEventId,
                ],
                "Copied sharaf definition \"{$source->name}\" as \"{$copy->name}\"",
                ['source_id' => $source->id, 'target_event_id' => $targetEventId]
            );

            return $copy->load([
                'sharafType',
                'sharafPositions' => fn ($q) => $q->orderBy('order'),
                'paymentDefinitions' => fn ($q) => $q->orderBy('name'),
            ]);
        });
    }

    /**
     * Copy every sharaf definition from one event onto another.
     * Definitions whose name already exists on the target event are skipped.
     *
     * @return array{copied: list<SharafDefinition>, skipped: list<array{name: string, reason: string}>}
     */
    public function copyFromEvent(int $sourceEventId, int $targetEventId): array
    {
        $sourceEvent = Event::find($sourceEventId);
        if (! $sourceEvent) {
            throw new InvalidArgumentException('Source event not found.');
        }

        $targetEvent = Event::find($targetEventId);
        if (! $targetEvent) {
            throw new InvalidArgumentException('Target event not found.');
        }

        if ($sourceEventId === $targetEventId) {
            throw new InvalidArgumentException('Source and target events must be different.');
        }

        $sources = SharafDefinition::where('event_id', $sourceEventId)
            ->with([
                'sharafPositions' => fn ($q) => $q->orderBy('order'),
                'paymentDefinitions' => fn ($q) => $q->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        $copied = [];
        $skipped = [];

        DB::transaction(function () use ($sources, $targetEventId, &$copied, &$skipped) {
            foreach ($sources as $source) {
                if (SharafDefinition::where('event_id', $targetEventId)->where('name', $source->name)->exists()) {
                    $skipped[] = [
                        'name' => $source->name,
                        'reason' => 'A sharaf definition with this name already exists on the target event.',
                    ];
                    continue;
                }

                $copy = SharafDefinition::create([
                    'event_id' => $targetEventId,
                    'sharaf_type_id' => $source->sharaf_type_id,
                    'name' => $source->name,
                    'key' => $source->key,
                    'description' => $source->description,
                ]);

                $this->cloneChildren($source, $copy);
                $copied[] = $copy->load([
                    'sharafType',
                    'sharafPositions' => fn ($q) => $q->orderBy('order'),
                    'paymentDefinitions' => fn ($q) => $q->orderBy('name'),
                ]);
            }
        });

        return [
            'copied' => $copied,
            'skipped' => $skipped,
        ];
    }

    private function assertNameAvailable(int $eventId, string $name): void
    {
        $exists = SharafDefinition::where('event_id', $eventId)->where('name', $name)->exists();
        if ($exists) {
            throw new InvalidArgumentException(
                "A sharaf definition named \"{$name}\" already exists on the target event."
            );
        }
    }

    private function cloneChildren(SharafDefinition $source, SharafDefinition $copy): void
    {
        foreach ($source->sharafPositions as $position) {
            SharafPosition::create([
                'sharaf_definition_id' => $copy->id,
                'name' => $position->name,
                'display_name' => $position->display_name,
                'capacity' => $position->capacity,
                'order' => $position->order,
            ]);
        }

        foreach ($source->paymentDefinitions as $paymentDefinition) {
            PaymentDefinition::create([
                'sharaf_definition_id' => $copy->id,
                'name' => $paymentDefinition->name,
                'description' => $paymentDefinition->description,
                'user_type' => $paymentDefinition->user_type,
            ]);
        }
    }
}
