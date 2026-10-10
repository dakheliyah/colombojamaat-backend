<?php

namespace App\Services;

use App\Exceptions\DuplicateSharafAssignmentException;
use App\Models\Census;
use App\Models\Currency;
use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\SharafPayment;
use App\Models\SharafPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SharafBulkImportService
{
    private const MAX_SLOTS = 10;

    private const MAX_ROWS = 500;

    public function __construct(
        protected SharafAllocationService $allocationService
    ) {}

    /**
     * Column guide for one sharaf definition. The sample CSV uses these headers.
     *
     * @return array{
     *     sharaf_definition_id: int,
     *     sharaf_definition_name: string,
     *     blocked_reason: string|null,
     *     notes: list<string>,
     *     columns: list<array{header: string, required: bool, group: string, description: string}>
     * }
     */
    public function columnGuide(SharafDefinition $definition): array
    {
        $plan = $this->plan($definition);

        return [
            'sharaf_definition_id' => $definition->id,
            'sharaf_definition_name' => $definition->name,
            'blocked_reason' => $plan['blocked_reason'],
            'notes' => $this->notes(),
            'columns' => array_map(fn (array $column) => [
                'header' => $column['header'],
                'required' => $column['required'],
                'group' => $column['group'],
                'description' => $column['description'],
            ], $plan['columns']),
        ];
    }

    public function templateCsv(SharafDefinition $definition): string
    {
        $plan = $this->plan($definition);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_column($plan['columns'], 'header'));
        fputcsv($handle, $this->exampleRow($definition, $plan));
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".($csv === false ? '' : $csv);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateContents(SharafDefinition $definition, string $contents): array
    {
        return $this->analyze($definition, $contents)['public'];
    }

    /**
     * Re-validates the file and imports every row, or imports nothing.
     *
     * @return array{ok: bool, validation: array<string, mixed>, sharaf_ids: list<int>}
     */
    public function importContents(SharafDefinition $definition, string $contents): array
    {
        $analyzed = $this->analyze($definition, $contents);
        if (!$analyzed['public']['can_import']) {
            return [
                'ok' => false,
                'validation' => $analyzed['public'],
                'sharaf_ids' => [],
            ];
        }

        return [
            'ok' => true,
            'validation' => $analyzed['public'],
            'sharaf_ids' => $this->persist($definition, $analyzed['rows']),
        ];
    }

    /**
     * @return array{public: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    private function analyze(SharafDefinition $definition, string $contents): array
    {
        $plan = $this->plan($definition);
        if ($plan['blocked_reason'] !== null) {
            return [
                'public' => $this->publicResult([$plan['blocked_reason']], []),
                'rows' => [],
            ];
        }

        $table = $this->readTable($contents);
        if ($table['headers'] === []) {
            return [
                'public' => $this->publicResult($table['errors'] !== [] ? $table['errors'] : ['The file has no header row.'], []),
                'rows' => [],
            ];
        }

        $fileErrors = $table['errors'];
        $expected = [];
        foreach ($plan['columns'] as $column) {
            $expected[mb_strtolower($column['header'])] = $column;
        }

        $headerProblems = $fileErrors;
        $present = [];
        foreach ($table['headers'] as $header) {
            $key = mb_strtolower($header);
            if (!isset($expected[$key])) {
                $headerProblems[] = "Unknown column \"{$header}\". Download a fresh sample for this sharaf.";
                continue;
            }
            $present[$key] = true;
        }
        foreach ($expected as $key => $column) {
            if (!isset($present[$key])) {
                $headerProblems[] = "Missing column \"{$column['header']}\". Download a fresh sample for this sharaf.";
            }
        }

        if ($headerProblems !== []) {
            return [
                'public' => $this->publicResult($headerProblems, []),
                'rows' => [],
            ];
        }

        if (count($table['records']) > self::MAX_ROWS) {
            $fileErrors[] = 'Upload at most '.self::MAX_ROWS.' sharafs at a time.';
            $table['records'] = array_slice($table['records'], 0, self::MAX_ROWS);
        }

        if ($table['records'] === []) {
            $fileErrors[] = 'The file has no sharaf rows. Replace the example row with the sharafs to import.';
        }

        $index = [];
        foreach ($table['headers'] as $position => $header) {
            $index[mb_strtolower($header)] = $position;
        }

        $knownCurrencies = $this->knownCurrencyCodes();
        $rows = [];
        foreach ($table['records'] as $record) {
            $rows[] = $this->parseRecord($record['line'], $record['cells'], $plan, $index, $knownCurrencies);
        }

        $this->applyCrossRowChecks($definition, $rows);

        return [
            'public' => $this->publicResult($fileErrors, $rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<string>  $fileErrors
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function publicResult(array $fileErrors, array $rows): array
    {
        $errorCount = count($fileErrors);
        $warningCount = 0;
        $publicRows = [];

        foreach ($rows as $row) {
            $errorCount += count($row['errors']);
            $warningCount += count($row['warnings']);
            $publicRows[] = [
                'line' => $row['line'],
                'capacity' => $row['capacity'],
                'token' => $row['token'],
                'comments' => $row['comments'],
                'hof_its' => $row['hof_its'],
                'hof_name' => $row['hof_name'],
                'people' => $row['people'],
                'payments' => $row['payments_preview'],
                'errors' => $row['errors'],
                'warnings' => $row['warnings'],
            ];
        }

        return [
            'can_import' => $fileErrors === [] && $errorCount === 0 && $publicRows !== [],
            'row_count' => count($publicRows),
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'file_errors' => array_values($fileErrors),
            'rows' => $publicRows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    private function persist(SharafDefinition $definition, array $rows): array
    {
        try {
            return DB::transaction(function () use ($definition, $rows) {
                SharafDefinition::query()->whereKey($definition->id)->lockForUpdate()->first();
                $rank = (int) Sharaf::query()
                    ->where('sharaf_definition_id', $definition->id)
                    ->max('rank');

                $ids = [];
                foreach ($rows as $row) {
                    if ($row['errors'] !== []) {
                        throw new RuntimeException('The file has errors and was not imported.');
                    }

                    $hofTaken = Sharaf::query()
                        ->where('sharaf_definition_id', $definition->id)
                        ->where('hof_its', $row['hof_its'])
                        ->exists();
                    if ($hofTaken) {
                        throw new RuntimeException("Row {$row['line']}: this HOF is already assigned to this sharaf definition.");
                    }

                    if ($row['token'] !== null) {
                        $tokenTaken = Sharaf::query()->where('token', $row['token'])->exists();
                        if ($tokenTaken) {
                            throw new RuntimeException("Row {$row['line']}: token is already in use.");
                        }
                    }

                    $rank++;
                    $sharaf = Sharaf::create([
                        'sharaf_definition_id' => $definition->id,
                        'rank' => $rank,
                        'name' => $row['hof_name'],
                        'capacity' => $row['capacity'],
                        'status' => 'confirmed',
                        'hof_its' => $row['hof_its'],
                        'token' => $row['token'],
                        'comments' => $row['comments'],
                    ]);

                    foreach ($row['members'] as $member) {
                        $this->allocationService->addMember(
                            $sharaf->id,
                            $member['position_id'],
                            $member['its'],
                            $member['slot'],
                            $member['name'],
                            $member['phone']
                        );
                    }

                    foreach ($row['payments'] as $payment) {
                        SharafPayment::create([
                            'sharaf_id' => $sharaf->id,
                            'payment_definition_id' => $payment['payment_definition_id'],
                            'payment_amount' => $payment['amount'],
                            'payment_status' => false,
                            'payment_currency' => $payment['currency'],
                        ]);
                    }

                    $ids[] = $sharaf->id;
                }

                return $ids;
            });
        } catch (DuplicateSharafAssignmentException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(SharafDefinition $definition): array
    {
        $positions = $definition->sharafPositions
            ->sortBy(fn (SharafPosition $position) => sprintf('%010d-%010d', (int) $position->order, (int) $position->id))
            ->values();

        $hof = $positions->first(fn (SharafPosition $position) => (int) $position->order === 1);
        $blocked = $hof === null
            ? 'This sharaf definition has no position with order 1, so a head of family cannot be assigned.'
            : null;

        $usedHeaders = [];
        $claim = function (string $header) use (&$usedHeaders): string {
            $candidate = $header;
            $suffix = 2;
            while (isset($usedHeaders[mb_strtolower($candidate)])) {
                $candidate = $header.' '.$suffix;
                $suffix++;
            }
            $usedHeaders[mb_strtolower($candidate)] = true;

            return $candidate;
        };

        $suggested = ($definition->default_capacity !== null && (int) $definition->default_capacity >= 1)
            ? ' This definition suggests '.(int) $definition->default_capacity.'.'
            : '';

        $columns = [
            [
                'header' => $claim('capacity'),
                'required' => true,
                'group' => 'sharaf',
                'kind' => 'capacity',
                'description' => 'Required. How many people this sharaf holds. Use a whole number of at least 1.'.$suggested,
            ],
            [
                'header' => $claim('token'),
                'required' => false,
                'group' => 'sharaf',
                'kind' => 'token',
                'description' => 'Optional. A unique tag for this sharaf, up to 50 characters. Leave blank if you do not use tokens.',
            ],
            [
                'header' => $claim('comments'),
                'required' => false,
                'group' => 'sharaf',
                'kind' => 'comments',
                'description' => 'Optional note stored on the sharaf.',
            ],
        ];

        $usedLabels = [];
        $seats = [];
        foreach ($positions as $position) {
            $label = $this->positionLabel($position, $usedLabels);
            $slotCount = $this->slotCount($position);
            for ($slot = 1; $slot <= $slotCount; $slot++) {
                $isHof = $hof !== null && $position->id === $hof->id && $slot === 1;
                $seatLabel = $label.' '.$slot;
                $headers = [
                    'its' => $claim($seatLabel.' ITS'),
                    'name' => $claim($seatLabel.' Name'),
                    'phone' => $claim($seatLabel.' Phone'),
                ];
                $itsDescription = $isHof
                    ? 'Required. Head of family ITS. Digits only, up to 8. This person is stored as the sharaf HOF. Leading zeros are ignored.'
                    : "Optional. {$label} seat {$slot}. Leave blank to skip this seat. Digits only, up to 8.";

                $columns[] = [
                    'header' => $headers['its'],
                    'required' => $isHof,
                    'group' => 'position',
                    'kind' => 'member',
                    'description' => $itsDescription,
                ];
                $columns[] = [
                    'header' => $headers['name'],
                    'required' => false,
                    'group' => 'position',
                    'kind' => 'member',
                    'description' => 'Optional. Stored name. If blank and the ITS is in census, the census name is used.',
                ];
                $columns[] = [
                    'header' => $headers['phone'],
                    'required' => false,
                    'group' => 'position',
                    'kind' => 'member',
                    'description' => 'Optional. If blank and the ITS is in census, the census mobile is used when it fits.',
                ];

                $seats[] = [
                    'position_id' => $position->id,
                    'slot' => $slot,
                    'label' => $label,
                    'is_hof' => $isHof,
                    'headers' => $headers,
                ];
            }
        }

        $payments = [];
        $paymentDefinitions = $definition->paymentDefinitions
            ->sortBy(fn ($payment) => mb_strtolower((string) $payment->name).'-'.$payment->id)
            ->values();

        foreach ($paymentDefinitions as $payment) {
            $name = trim(preg_replace('/\s+/', ' ', (string) $payment->name) ?? '');
            if ($name === '') {
                $name = 'Payment '.$payment->id;
            }
            $defaultCurrency = $payment->default_currency
                ? strtoupper((string) $payment->default_currency)
                : 'LKR';
            $usual = ($payment->default_amount !== null && (float) $payment->default_amount > 0)
                ? ' Usual amount is '.$payment->default_amount.' '.$defaultCurrency.'.'
                : '';
            $amountHeader = $claim($name.' Amount');
            $currencyHeader = $claim($name.' Currency');

            $columns[] = [
                'header' => $amountHeader,
                'required' => false,
                'group' => 'payment',
                'kind' => 'payment_amount',
                'description' => 'Optional commitment. Leave blank or 0 to skip. Use a number such as 1500 or 1500.50, without commas.'.$usual,
            ];
            $columns[] = [
                'header' => $currencyHeader,
                'required' => false,
                'group' => 'payment',
                'kind' => 'payment_currency',
                'description' => "Optional. Three-letter code such as LKR. When the amount is set and this is blank, {$defaultCurrency} is used.",
            ];

            $payments[] = [
                'payment_definition_id' => $payment->id,
                'name' => $name,
                'amount_header' => $amountHeader,
                'currency_header' => $currencyHeader,
                'default_currency' => $defaultCurrency,
            ];
        }

        return [
            'blocked_reason' => $blocked,
            'columns' => $columns,
            'seats' => $seats,
            'payments' => $payments,
        ];
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function positionLabel(SharafPosition $position, array &$used): string
    {
        $base = trim(preg_replace('/\s+/', ' ', (string) ($position->display_name ?: $position->name)) ?? '');
        if ($base === '') {
            $base = 'Position '.$position->id;
        }
        $label = $base;
        if (isset($used[mb_strtolower($label)])) {
            $label = $base.' ('.$position->id.')';
        }
        $used[mb_strtolower($label)] = true;

        return $label;
    }

    private function slotCount(SharafPosition $position): int
    {
        $capacity = $position->capacity;
        $count = ($capacity === null || (int) $capacity < 1) ? 1 : (int) $capacity;

        return min(self::MAX_SLOTS, $count);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    private function exampleRow(SharafDefinition $definition, array $plan): array
    {
        $capacity = ($definition->default_capacity !== null && (int) $definition->default_capacity >= 1)
            ? (string) (int) $definition->default_capacity
            : '1';

        $cells = [];
        foreach ($plan['columns'] as $column) {
            $cells[] = match ($column['kind']) {
                'capacity' => $capacity,
                'comments' => 'Replace this example row',
                default => '',
            };
        }

        foreach ($plan['seats'] as $seat) {
            if (!$seat['is_hof']) {
                continue;
            }
            foreach ($plan['columns'] as $index => $column) {
                if ($column['header'] === $seat['headers']['its']) {
                    $cells[$index] = '12345678';
                }
                if ($column['header'] === $seat['headers']['name']) {
                    $cells[$index] = 'Example Name';
                }
            }
        }

        return $cells;
    }

    /**
     * @return list<string>
     */
    private function notes(): array
    {
        return [
            'One row creates one sharaf for this definition.',
            'Rank is assigned automatically, after the sharafs already on this definition.',
            'Status is set to confirmed.',
            'The order-1 position, seat 1, is the head of family and is required.',
            'Other seats are optional. Leave the ITS blank to skip a seat.',
            'A takhmeen payment is saved only when its amount is greater than 0.',
            'The same HOF cannot be used twice on this definition. That is an error and blocks the import.',
            'If someone already has a sharaf on another definition in this miqaat, that is a warning. You can still approve the import.',
            'The sample file includes one example row. Replace it before you import.',
            'Upload at most 500 rows.',
        ];
    }

    /**
     * @return array{errors: list<string>, headers: list<string>, records: list<array{line: int, cells: list<string|null>}>}
     */
    private function readTable(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        if (trim($contents) === '') {
            return ['errors' => ['The file is empty.'], 'headers' => [], 'records' => []];
        }

        $firstLine = preg_split("/\r\n|\n|\r/", $contents, 2)[0] ?? '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $headerCells = fgetcsv($handle, 0, $delimiter);
        if ($headerCells === false) {
            fclose($handle);

            return ['errors' => ['The file has no header row.'], 'headers' => [], 'records' => []];
        }

        while ($headerCells !== [] && trim((string) end($headerCells)) === '') {
            array_pop($headerCells);
        }

        $errors = [];
        $headers = [];
        $seen = [];
        foreach ($headerCells as $cell) {
            $header = $this->normalizeHeader((string) $cell);
            if ($header === '') {
                $errors[] = 'The file has an empty column header.';
                continue;
            }
            $key = mb_strtolower($header);
            if (isset($seen[$key])) {
                $errors[] = "The column \"{$header}\" is repeated.";
                continue;
            }
            $seen[$key] = true;
            $headers[] = $header;
        }

        $records = [];
        $line = 1;
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($this->rowIsEmpty($cells)) {
                continue;
            }
            $records[] = ['line' => $line, 'cells' => $cells];
        }
        fclose($handle);

        return ['errors' => $errors, 'headers' => $headers, 'records' => $records];
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, mixed>  $plan
     * @param  array<string, int>  $index
     * @param  list<string>  $knownCurrencies
     * @return array<string, mixed>
     */
    private function parseRecord(int $line, array $cells, array $plan, array $index, array $knownCurrencies): array
    {
        $errors = [];
        $warnings = [];
        $value = function (string $header) use ($cells, $index): string {
            $key = mb_strtolower($header);
            if (!isset($index[$key])) {
                return '';
            }
            $position = $index[$key];
            if (!array_key_exists($position, $cells) || $cells[$position] === null) {
                return '';
            }

            return trim((string) $cells[$position]);
        };

        if (count($cells) > count($index)) {
            $hasExtra = false;
            foreach (array_slice($cells, count($index)) as $extra) {
                if (trim((string) $extra) !== '') {
                    $hasExtra = true;
                }
            }
            if ($hasExtra) {
                $errors[] = ['field' => 'row', 'message' => 'This row has values past the last column.'];
            }
        }

        $capacityHeader = $this->headerForKind($plan, 'capacity');
        $tokenHeader = $this->headerForKind($plan, 'token');
        $commentsHeader = $this->headerForKind($plan, 'comments');

        $capacityRaw = $value($capacityHeader);
        $capacity = null;
        if ($capacityRaw === '' || !preg_match('/^\d+$/', $capacityRaw) || (int) $capacityRaw < 1) {
            $errors[] = ['field' => $capacityHeader, 'message' => 'Capacity must be a whole number of at least 1.'];
        } elseif ((int) $capacityRaw > 9999) {
            $errors[] = ['field' => $capacityHeader, 'message' => 'Capacity must be 9999 or less.'];
        } else {
            $capacity = (int) $capacityRaw;
        }

        $tokenRaw = $value($tokenHeader);
        $token = $tokenRaw === '' ? null : $tokenRaw;
        if ($token !== null && mb_strlen($token) > 50) {
            $errors[] = ['field' => $tokenHeader, 'message' => 'Token must be 50 characters or less.'];
        }

        $commentsRaw = $value($commentsHeader);
        $comments = $commentsRaw === '' ? null : $commentsRaw;
        if ($comments !== null && mb_strlen($comments) > 5000) {
            $errors[] = ['field' => $commentsHeader, 'message' => 'Comments must be 5000 characters or less.'];
        }

        $members = [];
        $seenIts = [];
        foreach ($plan['seats'] as $seat) {
            $itsRaw = $value($seat['headers']['its']);
            $name = $value($seat['headers']['name']);
            $phone = $value($seat['headers']['phone']);
            $its = null;

            if ($itsRaw !== '') {
                if (!preg_match('/^\d{1,8}$/', $itsRaw)) {
                    $errors[] = ['field' => $seat['headers']['its'], 'message' => 'ITS must be 1 to 8 digits.'];
                } else {
                    $its = (string) (int) $itsRaw;
                    if ($its === '0') {
                        $errors[] = ['field' => $seat['headers']['its'], 'message' => 'ITS must be greater than 0.'];
                        $its = null;
                    }
                }
            } elseif ($name !== '' || $phone !== '') {
                $errors[] = ['field' => $seat['headers']['its'], 'message' => 'Enter an ITS for this seat, or clear the name and phone.'];
            } elseif ($seat['is_hof']) {
                $errors[] = ['field' => $seat['headers']['its'], 'message' => 'Head of family ITS is required.'];
            }

            if ($name !== '' && mb_strlen($name) > 255) {
                $errors[] = ['field' => $seat['headers']['name'], 'message' => 'Name must be 255 characters or less.'];
            }
            if ($phone !== '' && !preg_match('/^[0-9+\-()\s]{1,20}$/', $phone)) {
                $errors[] = ['field' => $seat['headers']['phone'], 'message' => 'Phone must be 20 characters or less and use digits, spaces, +, -, or parentheses.'];
            }

            if ($its === null) {
                continue;
            }

            if (isset($seenIts[$its])) {
                $errors[] = ['field' => $seat['headers']['its'], 'message' => 'This ITS is listed more than once on this row.'];
            }
            $seenIts[$its] = true;

            $members[] = [
                'position_id' => $seat['position_id'],
                'slot' => $seat['slot'],
                'label' => $seat['label'],
                'its' => $its,
                'name' => $name === '' ? null : $name,
                'phone' => $phone === '' ? null : $phone,
                'its_header' => $seat['headers']['its'],
                'is_hof' => $seat['is_hof'],
            ];
        }

        $payments = [];
        $paymentsPreview = [];
        foreach ($plan['payments'] as $payment) {
            $amountRaw = $value($payment['amount_header']);
            $currencyRaw = strtoupper($value($payment['currency_header']));
            $blankAmount = $amountRaw === '' || preg_match('/^0+(\.0+)?$/', $amountRaw) === 1;

            if ($blankAmount) {
                if ($currencyRaw !== '' && $amountRaw === '') {
                    $warnings[] = [
                        'field' => $payment['currency_header'],
                        'message' => 'Currency is set without an amount, so this payment will be skipped.',
                    ];
                }
                continue;
            }

            if (preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw) !== 1) {
                $errors[] = [
                    'field' => $payment['amount_header'],
                    'message' => 'Amount must be a number greater than 0, such as 1500 or 1500.50, without commas.',
                ];
                continue;
            }

            $currency = $currencyRaw !== '' ? $currencyRaw : $payment['default_currency'];
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                $errors[] = [
                    'field' => $payment['currency_header'],
                    'message' => 'Currency must be a three-letter code such as LKR.',
                ];
                continue;
            }
            if ($knownCurrencies !== [] && !in_array($currency, $knownCurrencies, true)) {
                $errors[] = [
                    'field' => $payment['currency_header'],
                    'message' => "Currency {$currency} is not in the currency list.",
                ];
                continue;
            }

            $amount = number_format((float) $amountRaw, 2, '.', '');
            $payments[] = [
                'payment_definition_id' => $payment['payment_definition_id'],
                'amount' => $amount,
                'currency' => $currency,
            ];
            $paymentsPreview[] = [
                'name' => $payment['name'],
                'amount' => $amount,
                'currency' => $currency,
            ];
        }

        $hofIts = null;
        $hofName = null;
        foreach ($members as $member) {
            if ($member['is_hof']) {
                $hofIts = $member['its'];
                $hofName = $member['name'];
            }
        }

        return [
            'line' => $line,
            'capacity' => $capacity,
            'token' => $token,
            'comments' => $comments,
            'hof_its' => $hofIts,
            'hof_name' => $hofName,
            'members' => $members,
            'payments' => $payments,
            'payments_preview' => $paymentsPreview,
            'people' => [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function applyCrossRowChecks(SharafDefinition $definition, array &$rows): void
    {
        $hofLines = [];
        $tokenLines = [];
        $itsLines = [];
        $allIts = [];

        foreach ($rows as $row) {
            if ($row['hof_its'] !== null) {
                $hofLines[$row['hof_its']][] = $row['line'];
            }
            if ($row['token'] !== null) {
                $tokenLines[$row['token']][] = $row['line'];
            }
            foreach ($row['members'] as $member) {
                $itsLines[$member['its']][] = $row['line'];
                $allIts[$member['its']] = true;
            }
        }

        $existingHofs = $hofLines === []
            ? []
            : Sharaf::query()
                ->where('sharaf_definition_id', $definition->id)
                ->whereIn('hof_its', array_keys($hofLines))
                ->pluck('hof_its')
                ->all();
        $existingHofSet = array_fill_keys($existingHofs, true);

        $existingTokens = $tokenLines === []
            ? []
            : Sharaf::query()
                ->whereIn('token', array_keys($tokenLines))
                ->pluck('token')
                ->all();
        $existingTokenSet = array_fill_keys($existingTokens, true);

        $census = $this->censusByIts(array_keys($allIts));
        $miqaatId = $definition->event?->miqaat_id;
        $hofElsewhere = $this->hofsElsewhere($definition->id, $miqaatId, array_keys($hofLines));
        $membersElsewhere = $this->membersElsewhere($miqaatId, array_keys($allIts));

        foreach ($rows as &$row) {
            foreach ($row['members'] as &$member) {
                $record = $census[$member['its']] ?? null;
                if ($record === null) {
                    $row['warnings'][] = [
                        'field' => $member['its_header'],
                        'message' => "No census record for ITS {$member['its']}.",
                    ];
                } else {
                    if ($member['name'] === null) {
                        $censusName = trim((string) ($record['name'] ?? ''));
                        if ($censusName !== '') {
                            $member['name'] = mb_substr($censusName, 0, 255);
                        }
                    }
                    if ($member['phone'] === null) {
                        $mobile = trim((string) ($record['mobile'] ?? ''));
                        if ($mobile !== '' && preg_match('/^[0-9+\-()\s]{1,20}$/', $mobile) === 1) {
                            $member['phone'] = $mobile;
                        }
                    }
                }

                $otherLines = array_values(array_unique(array_filter(
                    $itsLines[$member['its']] ?? [],
                    fn (int $line) => $line !== $row['line']
                )));
                $hofDupLines = $hofLines[$member['its']] ?? [];
                $isDuplicateHof = count($hofDupLines) > 1 && in_array($row['line'], $hofDupLines, true);
                if ($otherLines !== [] && !$isDuplicateHof) {
                    $row['warnings'][] = [
                        'field' => $member['its_header'],
                        'message' => 'Also listed on row '.implode(', ', $otherLines).' in this file.',
                    ];
                }

                foreach ($membersElsewhere[$member['its']] ?? [] as $existing) {
                    $row['warnings'][] = [
                        'field' => $member['its_header'],
                        'message' => "ITS {$member['its']} is already on {$existing['name']} (rank {$existing['rank']}) in this miqaat.",
                    ];
                }
            }
            unset($member);

            if ($row['hof_its'] !== null) {
                $hofMember = null;
                foreach ($row['members'] as $member) {
                    if ($member['is_hof']) {
                        $hofMember = $member;
                        $row['hof_name'] = $member['name'];
                    }
                }

                if (isset($existingHofSet[$row['hof_its']])) {
                    $row['errors'][] = [
                        'field' => $hofMember['its_header'] ?? 'hof_its',
                        'message' => 'This HOF is already assigned to a sharaf in this definition.',
                    ];
                }

                $duplicateLines = array_values(array_filter(
                    $hofLines[$row['hof_its']] ?? [],
                    fn (int $line) => $line !== $row['line']
                ));
                if ($duplicateLines !== []) {
                    $row['errors'][] = [
                        'field' => $hofMember['its_header'] ?? 'hof_its',
                        'message' => 'This HOF is also used on row '.implode(', ', $duplicateLines).'.',
                    ];
                }

                foreach ($hofElsewhere[$row['hof_its']] ?? [] as $existing) {
                    $row['warnings'][] = [
                        'field' => $hofMember['its_header'] ?? 'hof_its',
                        'message' => "This HOF already has {$existing['name']} (rank {$existing['rank']}) in this miqaat.",
                    ];
                }
            }

            if ($row['token'] !== null) {
                $tokenHeader = 'token';
                if (isset($existingTokenSet[$row['token']])) {
                    $row['errors'][] = [
                        'field' => $tokenHeader,
                        'message' => 'Token is already used by another sharaf.',
                    ];
                }
                $duplicateLines = array_values(array_filter(
                    $tokenLines[$row['token']] ?? [],
                    fn (int $line) => $line !== $row['line']
                ));
                if ($duplicateLines !== []) {
                    $row['errors'][] = [
                        'field' => $tokenHeader,
                        'message' => 'Token is also used on row '.implode(', ', $duplicateLines).'.',
                    ];
                }
            }

            $people = [];
            foreach ($row['members'] as $member) {
                $people[] = [
                    'label' => $member['label'].' '.$member['slot'],
                    'slot' => $member['slot'],
                    'its' => $member['its'],
                    'name' => $member['name'],
                ];
            }
            $row['people'] = $people;
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function headerForKind(array $plan, string $kind): string
    {
        foreach ($plan['columns'] as $column) {
            if ($column['kind'] === $kind) {
                return $column['header'];
            }
        }

        return $kind;
    }

    /**
     * @param  list<string>  $itsIds
     * @return array<string, array{name: ?string, mobile: ?string}>
     */
    private function censusByIts(array $itsIds): array
    {
        if ($itsIds === [] || !Schema::hasTable('census')) {
            return [];
        }

        $records = [];
        foreach (Census::query()->whereIn('its_id', $itsIds)->get(['its_id', 'name', 'mobile']) as $record) {
            $records[(string) $record->its_id] = [
                'name' => $record->name,
                'mobile' => $record->mobile,
            ];
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    private function knownCurrencyCodes(): array
    {
        if (!Schema::hasTable('currencies')) {
            return [];
        }

        return Currency::query()
            ->pluck('code')
            ->map(fn ($code) => strtoupper((string) $code))
            ->all();
    }

    /**
     * @param  list<string>  $hofIts
     * @return array<string, list<array{name: string, rank: int}>>
     */
    private function hofsElsewhere(int $definitionId, ?int $miqaatId, array $hofIts): array
    {
        if ($miqaatId === null || $hofIts === []) {
            return [];
        }

        $grouped = [];
        $existing = DB::table('sharafs as s')
            ->join('sharaf_definitions as sd', 's.sharaf_definition_id', '=', 'sd.id')
            ->join('events as e', 'sd.event_id', '=', 'e.id')
            ->where('e.miqaat_id', $miqaatId)
            ->where('sd.id', '!=', $definitionId)
            ->whereIn('s.hof_its', $hofIts)
            ->get(['s.hof_its', 's.rank', 'sd.name']);

        foreach ($existing as $row) {
            $grouped[(string) $row->hof_its][] = [
                'name' => (string) $row->name,
                'rank' => (int) $row->rank,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<string>  $itsIds
     * @return array<string, list<array{name: string, rank: int}>>
     */
    private function membersElsewhere(?int $miqaatId, array $itsIds): array
    {
        if ($miqaatId === null || $itsIds === []) {
            return [];
        }

        $grouped = [];
        $existing = DB::table('sharaf_members as sm')
            ->join('sharafs as s', 'sm.sharaf_id', '=', 's.id')
            ->join('sharaf_definitions as sd', 's.sharaf_definition_id', '=', 'sd.id')
            ->join('events as e', 'sd.event_id', '=', 'e.id')
            ->where('e.miqaat_id', $miqaatId)
            ->whereIn('sm.its_id', $itsIds)
            ->get(['sm.its_id', 's.rank', 'sd.name']);

        foreach ($existing as $row) {
            $grouped[(string) $row->its_id][] = [
                'name' => (string) $row->name,
                'rank' => (int) $row->rank,
            ];
        }

        return $grouped;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = trim($header);

        return preg_replace('/\s+/', ' ', $header) ?? $header;
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
