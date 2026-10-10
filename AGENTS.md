# Colombo Jamaat API

Laravel 12 / PHP 8.2 backend for Bethak. Frontend is `/Users/murtaza/Documents/colombojamaat-frontend`.

## Commands

```bash
php artisan serve        # confirm port (8000 vs 8001; Vite proxies 8001)
php artisan test
php artisan migrate
php artisan db:backup    # gzipped MySQL dump → storage/app/backups (keeps newest 3)
```

### DB backups (production VPS)

Scheduled in `routes/console.php`: every 30 minutes, Asia/Colombo, **08:00–02:00 inclusive**, quiet **02:30–07:30**. Requires `mysqldump` on PATH and a system cron:

```cron
* * * * * cd /path/to/colombojamaat-backend && php artisan schedule:run >> /dev/null 2>&1
```

Dumps are local-only under `storage/app/backups/` (gitignored). Log: `storage/logs/db-backup.log`.

## Layout

- `routes/api.php` — all HTTP routes
- `app/Http/Controllers/` — thin controllers
- `app/Services/` — allocation, payments, wajebaat, reporting, sila fitra, mapping
- `app/Models/` — Eloquent
- `app/Http/Middleware/ResolveUserFromCookie.php` — `user.from.cookie`
- `database/migrations/` — schema

## Auth

Cookie `user` = ITS number. `GET /api/auth/session` returns `{ its_no }`. Protected sharaf-definition listing uses `user.from.cookie` and filters by the user's sharaf types.

## Graphify

`graphify-out/GRAPH_REPORT.md` and `graphify-out/graph.html`. Query with `/graphify query "..."` from this repo.
