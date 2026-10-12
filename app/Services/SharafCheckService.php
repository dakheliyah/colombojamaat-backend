<?php

namespace App\Services;

use App\Models\Sharaf;
use App\Models\SharafCheck;
use App\Models\SharafCheckDefinition;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SharafCheckService
{
    /**
     * Mark a sharaf check done or not done.
     * The definition must belong to the sharaf's definition.
     * A configured webhook is notified after the row is saved.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function toggleByDefinitionId(
        int $sharafId,
        int $checkDefinitionId,
        bool $done,
        ?string $checkedByIts = null,
        ?string $notes = null
    ): SharafCheck {
        $sharaf = Sharaf::findOrFail($sharafId);

        $definition = SharafCheckDefinition::where('id', $checkDefinitionId)
            ->where('sharaf_definition_id', $sharaf->sharaf_definition_id)
            ->firstOrFail();

        $check = $this->writeStatus($sharaf, $definition, $done, $checkedByIts, $notes);
        $this->attachWebhookResult($check, $this->notifyWebhook($sharaf, $definition, $check));

        return $check;
    }

    /**
     * Pull done/not-done from the definition's status API and store it.
     * The webhook runs only when the stored status changes.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws RuntimeException
     */
    public function refreshFromApi(
        int $sharafId,
        int $checkDefinitionId,
        ?string $checkedByIts = null
    ): SharafCheck {
        $sharaf = Sharaf::findOrFail($sharafId);

        $definition = SharafCheckDefinition::where('id', $checkDefinitionId)
            ->where('sharaf_definition_id', $sharaf->sharaf_definition_id)
            ->firstOrFail();

        if (! is_string($definition->status_api_url) || trim($definition->status_api_url) === '') {
            throw new RuntimeException('This check has no status API URL.');
        }

        $done = $this->fetchStatus($sharaf, $definition);
        $existing = SharafCheck::query()
            ->where('sharaf_id', $sharaf->id)
            ->where('sharaf_check_definition_id', $definition->id)
            ->first();
        $changed = $existing === null || (bool) $existing->is_done !== $done;

        $check = $this->writeStatus($sharaf, $definition, $done, $checkedByIts, null);
        $this->attachWebhookResult(
            $check,
            $changed ? $this->notifyWebhook($sharaf, $definition, $check) : null
        );

        return $check;
    }

    private function writeStatus(
        Sharaf $sharaf,
        SharafCheckDefinition $definition,
        bool $done,
        ?string $checkedByIts,
        ?string $notes
    ): SharafCheck {
        $check = SharafCheck::firstOrNew([
            'sharaf_id' => $sharaf->id,
            'sharaf_check_definition_id' => $definition->id,
        ]);

        $check->is_done = $done;
        if ($done) {
            $check->checked_by_its = $checkedByIts;
            $check->checked_at = now();
            if ($notes !== null) {
                $check->notes = $notes;
            }
        } else {
            $check->checked_by_its = null;
            $check->checked_at = null;
        }

        $check->save();

        return $check;
    }

    /**
     * @throws RuntimeException
     */
    private function fetchStatus(Sharaf $sharaf, SharafCheckDefinition $definition): bool
    {
        $template = (string) $definition->status_api_url;
        $url = $this->applyPlaceholders($template, $sharaf, $definition);
        $alreadyNamesIts = str_contains($template, 'its_id=')
            || str_contains($template, '{its_id}')
            || str_contains($template, '{hof_its}');
        $query = $alreadyNamesIts ? [] : ['its_id' => (string) $sharaf->hof_its];

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get($url, $query);
        } catch (ConnectionException $e) {
            Log::warning('Sharaf check status API failed', [
                'sharaf_id' => $sharaf->id,
                'check_definition_id' => $definition->id,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Status API did not respond.');
        }

        if (! $response->successful()) {
            Log::warning('Sharaf check status API returned an error', [
                'sharaf_id' => $sharaf->id,
                'check_definition_id' => $definition->id,
                'status' => $response->status(),
            ]);
            throw new RuntimeException('Status API returned an error.');
        }

        return $this->readDoneFlag($response->json());
    }

    /**
     * @return bool|null true when delivered, false when the call failed, null when no webhook is configured
     */
    private function notifyWebhook(Sharaf $sharaf, SharafCheckDefinition $definition, SharafCheck $check): ?bool
    {
        if (! is_string($definition->webhook_url) || trim($definition->webhook_url) === '') {
            return null;
        }

        $url = $this->applyPlaceholders($definition->webhook_url, $sharaf, $definition);

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->post($url, [
                    'event' => 'sharaf_check.updated',
                    'sharaf_id' => (int) $sharaf->id,
                    'sharaf_check_definition_id' => (int) $definition->id,
                    'name' => (string) $definition->name,
                    'hof_its' => (string) $sharaf->hof_its,
                    'is_done' => (bool) $check->is_done,
                    'checked_by_its' => $check->checked_by_its,
                    'checked_at' => $check->checked_at?->toIso8601String(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Sharaf check webhook failed', [
                'sharaf_id' => $sharaf->id,
                'check_definition_id' => $definition->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Sharaf check webhook returned an error', [
                'sharaf_id' => $sharaf->id,
                'check_definition_id' => $definition->id,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    private function attachWebhookResult(SharafCheck $check, ?bool $delivered): void
    {
        $check->setAttribute('webhook_notified', $delivered);
    }

    private function applyPlaceholders(string $template, Sharaf $sharaf, SharafCheckDefinition $definition): string
    {
        return strtr($template, [
            '{its_id}' => rawurlencode((string) $sharaf->hof_its),
            '{hof_its}' => rawurlencode((string) $sharaf->hof_its),
            '{sharaf_id}' => (string) $sharaf->id,
            '{check_definition_id}' => (string) $definition->id,
            '{check_name}' => rawurlencode((string) $definition->name),
        ]);
    }

    /**
     * @throws RuntimeException
     */
    private function readDoneFlag(mixed $data): bool
    {
        if (is_bool($data)) {
            return $data;
        }

        if (! is_array($data)) {
            throw new RuntimeException('Status API response was not a done/not-done flag.');
        }

        foreach (['is_done', 'cleared', 'is_cleared', 'done'] as $key) {
            if (array_key_exists($key, $data)) {
                return (bool) $data[$key];
            }
        }

        $nested = $data['data'] ?? null;
        if (is_array($nested)) {
            foreach (['is_done', 'cleared', 'is_cleared', 'done'] as $key) {
                if (array_key_exists($key, $nested)) {
                    return (bool) $nested[$key];
                }
            }
        }

        throw new RuntimeException('Status API response was not a done/not-done flag.');
    }
}
