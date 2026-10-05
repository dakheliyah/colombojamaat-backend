<?php

namespace App\Services;

use App\Models\Census;

class CensusRelativesService
{
    /**
     * Person plus linked spouse, father, and mother.
     * Spouse uses spouse_its, then a reverse row whose spouse_its points here.
     *
     * @return array{person: array, spouse: array|null, father: array|null, mother: array|null}|null
     */
    public function forIts(string $itsId): ?array
    {
        $person = Census::query()->where('its_id', $itsId)->first();
        if (! $person) {
            return null;
        }

        return [
            'person' => $this->brief($person),
            'spouse' => $this->spouse($person),
            'father' => $this->linked($person->father_its, (string) $person->its_id),
            'mother' => $this->linked($person->mother_its, (string) $person->its_id),
        ];
    }

    private function spouse(Census $person): ?array
    {
        $linked = $this->normalizeIts($person->spouse_its);
        if ($linked !== null && $linked !== (string) $person->its_id) {
            return $this->linkedOrStub($linked);
        }

        $candidates = Census::query()
            ->where('spouse_its', $person->its_id)
            ->where('its_id', '!=', $person->its_id)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $personGender = $this->genderKind($person->gender);
        $opposite = $candidates->first(function (Census $row) use ($personGender) {
            $kind = $this->genderKind($row->gender);

            return $personGender !== null && $kind !== null && $kind !== $personGender;
        });

        return $this->brief($opposite ?? $candidates->first());
    }

    private function linked(mixed $its, string $selfIts): ?array
    {
        $normalized = $this->normalizeIts($its);
        if ($normalized === null || $normalized === $selfIts) {
            return null;
        }

        return $this->linkedOrStub($normalized);
    }

    private function linkedOrStub(string $its): array
    {
        $row = Census::query()->where('its_id', $its)->first();
        if ($row) {
            return $this->brief($row);
        }

        return [
            'its_id' => $its,
            'name' => null,
            'mobile' => null,
            'gender' => null,
        ];
    }

    /**
     * @return array{its_id: string, name: string|null, mobile: string|null, gender: string|null}
     */
    private function brief(Census $row): array
    {
        return [
            'its_id' => (string) $row->its_id,
            'name' => $row->name,
            'mobile' => $row->mobile,
            'gender' => $row->gender,
        ];
    }

    private function normalizeIts(mixed $its): ?string
    {
        $value = trim((string) ($its ?? ''));

        return $value === '' ? null : $value;
    }

    private function genderKind(mixed $gender): ?string
    {
        $value = strtolower(trim((string) ($gender ?? '')));
        if ($value === 'm' || $value === 'male' || str_starts_with($value, 'male')) {
            return 'male';
        }
        if ($value === 'f' || $value === 'female' || str_starts_with($value, 'female')) {
            return 'female';
        }

        return null;
    }
}
