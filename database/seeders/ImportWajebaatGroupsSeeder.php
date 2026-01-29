<?php

namespace Database\Seeders;

use App\Enums\WajebaatGroupType;
use App\Models\WajebaatGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportWajebaatGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Imports wajebaat_groups from CSV file.
     * Auto-generates wg_id for each unique (miqaat_id, master_its) combination.
     * Auto-assigns id (database auto-increment).
     */
    public function run(): void
    {
        $csvPath = $this->command->ask('Enter the full path to the CSV file', '/Users/murtaza/Downloads/wajebaat_groups.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error("Could not open CSV file: {$csvPath}");
            return;
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            $this->command->error("Could not read CSV header");
            fclose($handle);
            return;
        }

        // Map header columns to indices
        $columnMap = array_flip($header);
        
        // Required columns
        $requiredColumns = ['miqaat_id', 'master_its', 'its_id'];
        foreach ($requiredColumns as $col) {
            if (!isset($columnMap[$col])) {
                $this->command->error("Missing required column: {$col}");
                fclose($handle);
                return;
            }
        }

        $rows = [];
        $groupMap = []; // Maps (miqaat_id, master_its) to wg_id
        $nextWgId = 1;
        $skipped = 0;
        $errors = [];

        // Read all rows
        $lineNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            if (count($row) < count($header)) {
                $skipped++;
                continue; // Skip malformed rows
            }

            $miqaatId = (int) trim($row[$columnMap['miqaat_id']] ?? '');
            $masterIts = trim($row[$columnMap['master_its']] ?? '');
            $itsId = trim($row[$columnMap['its_id']] ?? '');
            $groupName = trim($row[$columnMap['group_name']] ?? '');
            $groupType = trim($row[$columnMap['group_type']] ?? '');

            // Skip rows with missing required data
            if (empty($miqaatId) || empty($masterIts) || empty($itsId)) {
                $skipped++;
                continue;
            }

            // Generate wg_id for each unique (miqaat_id, master_its) combination
            $groupKey = "{$miqaatId}:{$masterIts}";
            if (!isset($groupMap[$groupKey])) {
                // Get the next available wg_id for this miqaat
                $maxWgId = WajebaatGroup::where('miqaat_id', $miqaatId)->max('wg_id') ?? 0;
                $groupMap[$groupKey] = max($nextWgId, $maxWgId + 1);
                $nextWgId = $groupMap[$groupKey] + 1;
            }

            $wgId = $groupMap[$groupKey];

            // Handle group_name (can be empty/null)
            $groupNameValue = !empty($groupName) ? $groupName : null;

            // Handle group_type enum (validate or skip if invalid)
            $groupTypeValue = null;
            if (!empty($groupType)) {
                try {
                    $groupTypeEnum = WajebaatGroupType::from($groupType);
                    $groupTypeValue = $groupTypeEnum->value;
                } catch (\ValueError $e) {
                    $errors[] = "Line {$lineNumber}: Invalid group_type '{$groupType}' - skipping row";
                    $skipped++;
                    continue;
                }
            }

            $rows[] = [
                'wg_id' => $wgId,
                'group_name' => $groupNameValue,
                'group_type' => $groupTypeValue,
                'miqaat_id' => $miqaatId,
                'master_its' => $masterIts,
                'its_id' => $itsId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($handle);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->command->warn($error);
            }
        }

        if (empty($rows)) {
            $this->command->warn("No valid rows found in CSV");
            return;
        }

        // Insert in batches
        $this->command->info("Importing " . count($rows) . " group membership records...");
        if ($skipped > 0) {
            $this->command->warn("Skipped {$skipped} invalid rows");
        }
        
        $imported = 0;
        DB::transaction(function () use ($rows, &$imported) {
            foreach ($rows as $row) {
                try {
                    WajebaatGroup::updateOrCreate(
                        [
                            'miqaat_id' => $row['miqaat_id'],
                            'its_id' => $row['its_id'],
                        ],
                        $row
                    );
                    $imported++;
                } catch (\Exception $e) {
                    $this->command->error("Error importing row: " . $e->getMessage());
                }
            }
        });

        $this->command->info("Successfully imported {$imported} group membership records!");
        $this->command->info("Created " . count($groupMap) . " unique groups (based on master_its).");
    }
}
