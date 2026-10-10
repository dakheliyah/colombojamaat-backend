<?php

// Read by `php artisan db:backup`. Values must live here (not env() in the
// command) so they still apply when the config is cached.
return [

    'enabled' => filter_var(env('DB_BACKUP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'mysqldump' => env('DB_BACKUP_MYSQLDUMP', 'mysqldump'),

    'path' => env('DB_BACKUP_PATH', storage_path('app/backups')),

    'keep' => (int) env('DB_BACKUP_KEEP', 3),

    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 600),

];
