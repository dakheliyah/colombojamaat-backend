<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\SplFileInfo;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Dump the MySQL database to a gzipped SQL file and keep only the newest N backups';

    public function handle(): int
    {
        if (! filter_var(env('DB_BACKUP_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->info('Database backups are disabled (DB_BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql' && ($config['driver'] ?? null) !== 'mariadb') {
            $this->error("Database backups require mysql/mariadb; current driver is [{$config['driver']}].");

            return self::FAILURE;
        }

        $mysqldump = env('DB_BACKUP_MYSQLDUMP', 'mysqldump');
        $directory = env('DB_BACKUP_PATH', storage_path('app/backups'));
        $keep = max(1, (int) env('DB_BACKUP_KEEP', 3));

        File::ensureDirectoryExists($directory);

        $lockHandle = fopen($directory.DIRECTORY_SEPARATOR.'.db-backup.lock', 'c');
        if ($lockHandle === false || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $this->warn('Another backup is already running; skipping.');

            if (is_resource($lockHandle)) {
                fclose($lockHandle);
            }

            return self::SUCCESS;
        }

        try {
            $database = $config['database'];
            $timestamp = now('Asia/Colombo')->format('Y-m-d_H-i');
            $target = rtrim($directory, DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR
                ."{$database}_{$timestamp}.sql.gz";

            $arguments = [
                $mysqldump,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--hex-blob',
                '--no-tablespaces',
            ];

            if (! empty($config['unix_socket'])) {
                $arguments[] = '--socket='.$config['unix_socket'];
            } else {
                $arguments[] = '--host='.($config['host'] ?? '127.0.0.1');
                $arguments[] = '--port='.(string) ($config['port'] ?? 3306);
            }

            $arguments[] = '--user='.($config['username'] ?? 'root');
            $arguments[] = $database;

            $this->info("Creating backup: {$target}");

            $result = Process::env([
                'MYSQL_PWD' => (string) ($config['password'] ?? ''),
            ])
                ->timeout((int) env('DB_BACKUP_TIMEOUT', 600))
                ->run($arguments);

            if ($result->failed()) {
                $this->error('mysqldump failed.');
                $this->error(trim($result->errorOutput()) ?: trim($result->output()));

                return self::FAILURE;
            }

            $compressed = gzencode($result->output(), 9);
            if ($compressed === false) {
                $this->error('Failed to gzip the database dump.');

                return self::FAILURE;
            }

            if (File::put($target, $compressed) === false) {
                $this->error("Failed to write backup file: {$target}");

                return self::FAILURE;
            }

            $this->info('Backup written ('.File::size($target).' bytes).');
            $this->pruneOldBackups($directory, $keep);

            return self::SUCCESS;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function pruneOldBackups(string $directory, int $keep): void
    {
        $files = collect(File::files($directory))
            ->filter(fn (SplFileInfo $file) => str_ends_with($file->getFilename(), '.sql.gz'))
            ->sortByDesc(fn (SplFileInfo $file) => $file->getMTime())
            ->values();

        $files->slice($keep)->each(function (SplFileInfo $file) {
            File::delete($file->getPathname());
            $this->line("Deleted old backup: {$file->getFilename()}");
        });

        $this->info("Retention: kept newest {$keep} backup(s).");
    }
}
