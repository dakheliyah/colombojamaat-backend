# Colombo Jamaat API

Laravel 12 / PHP 8.2 backend for Bethak. Frontend is `/Users/murtaza/Documents/colombojamaat-frontend`.

## Commands

```bash
php artisan serve        # confirm port (8000 vs 8001; Vite proxies 8001)
php artisan test
php artisan migrate
```

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
