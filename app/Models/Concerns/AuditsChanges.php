<?php

namespace App\Models\Concerns;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

trait AuditsChanges
{
    public static function bootAuditsChanges(): void
    {
        static::created(function (Model $model) {
            app(AuditLogService::class)->recordCreated($model);
        });

        static::updated(function (Model $model) {
            app(AuditLogService::class)->recordUpdated($model);
        });

        static::deleted(function (Model $model) {
            app(AuditLogService::class)->recordDeleted($model);
        });
    }
}
