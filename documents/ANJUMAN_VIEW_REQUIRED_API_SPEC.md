# Anjuman View – Required API Spec (Backend)

This document specifies **new or extended API endpoints** required for the Anjuman user-type view. The frontend will use these once implemented. All endpoints follow the same conventions as the existing API (base URL `/api`, success `{ "success": true, "data": ... }`, errors `{ "success": false, "error": "...", "message": "..." }`).

---

## 1. Optional: Add `user_type` to List Miqaat Check Definitions

**Endpoint (existing):** `GET /api/miqaat-check-definitions`

**Change:** Add an optional query parameter so the Anjuman view can fetch only definitions assigned to the Anjuman user type.

**Query parameters (add one):**

| Parameter   | Type   | Required | Description |
|------------|--------|----------|-------------|
| `user_type`| string | No       | Filter by `user_type` (e.g. `Anjuman`). Values should match the `user_type` enum in `miqaat_check_definitions` (e.g. `BS`, `Admin`, `Help Desk`, `Anjuman`, `Finance`). |

**Example:** `GET /api/miqaat-check-definitions?miqaat_id=1&user_type=Anjuman&per_page=100`

**Response:** Unchanged. Paginated list; each item may include `user_type` when present in the table.

**Notes:** The frontend already sends `user_type` in this request. If the backend already supports it, no change is needed; otherwise add this filter and document it in Swagger.

---

## 2. List Miqaat Checks for a Person (by Miqaat)

**Endpoint:** `GET /api/miqaats/{miqaat_id}/miqaat-checks`

**Description:** Returns all `miqaat_checks` for a given person (its_id) within the scope of a miqaat. The miqaat is used to restrict checks to those whose definition belongs to that miqaat (miqaat_checks do not store miqaat_id; it is implied via miqaat_check_definitions.miqaat_id). Used by the Anjuman view to show which definitions are cleared/uncleared for the entered ITS number.

**URL parameters:**

| Parameter   | Type    | Required | Description |
|------------|---------|----------|-------------|
| `miqaat_id`| integer | Yes      | Miqaat ID (must exist in `miqaats`). |

**Query parameters:**

| Parameter | Type   | Required | Description |
|----------|--------|----------|-------------|
| `its_id` | string | Yes      | ITS ID of the person (census identifier). |

**Example:** `GET /api/miqaats/1/miqaat-checks?its_id=30361286`

**Success response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "its_id": "30361286",
      "mcd_id": 5,
      "is_cleared": true,
      "cleared_by_its": "30360001",
      "cleared_at": "2026-01-30T10:00:00.000000Z",
      "notes": null,
      "created_at": "2026-01-30T09:00:00.000000Z",
      "updated_at": "2026-01-30T10:00:00.000000Z"
    },
    {
      "id": 2,
      "its_id": "30361286",
      "mcd_id": 7,
      "is_cleared": false,
      "cleared_by_its": null,
      "cleared_at": null,
      "notes": null,
      "created_at": "2026-01-30T09:00:00.000000Z",
      "updated_at": "2026-01-30T09:00:00.000000Z"
    }
  ]
}
```

**Business logic:**

1. Query `miqaat_checks` where `its_id` = query param and `mcd_id` is in (select `mcd_id` from `miqaat_check_definitions` where `miqaat_id` = path param).
2. Return all matching rows (no pagination required for this use case; typical count is small).
3. If no rows exist, return `data: []`.

**Error responses:**

- **404** – Miqaat not found: `{ "success": false, "error": "NOT_FOUND", "message": "Miqaat not found." }`
- **422** – Missing `its_id`: `{ "success": false, "error": "VALIDATION_ERROR", "message": "The its_id parameter is required." }`

---

## 3. Upsert a Miqaat Check (Clear / Unclear)

**Endpoint:** `PUT /api/miqaats/{miqaat_id}/miqaat-checks` or `POST /api/miqaats/{miqaat_id}/miqaat-checks`

**Description:** Creates or updates a single `miqaat_checks` row for a person and a check definition. Uniqueness is on `(its_id, mcd_id)`; if a row exists, update it; otherwise insert. Used by the Anjuman view when the user toggles “clear” or “unclear” for a definition.

**URL parameters:**

| Parameter   | Type    | Required | Description |
|------------|---------|----------|-------------|
| `miqaat_id`| integer | Yes      | Miqaat ID (must exist; used to validate that `mcd_id` belongs to this miqaat). |

**Request body (JSON):**

| Field          | Type    | Required | Description |
|----------------|---------|----------|-------------|
| `its_id`       | string  | Yes      | ITS ID of the person. |
| `mcd_id`       | integer | Yes      | Check definition ID (must exist in `miqaat_check_definitions` and have `miqaat_id` = path `miqaat_id`). |
| `is_cleared`   | boolean | Yes      | Whether the check is cleared (true) or not (false). |
| `cleared_by_its` | string | No       | ITS of the user performing the action (e.g. current Anjuman user). |
| `notes`        | string  | No       | Optional notes. |

**Example request:**

```json
{
  "its_id": "30361286",
  "mcd_id": 5,
  "is_cleared": true,
  "cleared_by_its": "30360001",
  "notes": null
}
```

**Success response (200 OK or 201 Created):**

Return the created or updated row so the frontend can refresh UI if needed.

```json
{
  "success": true,
  "data": {
    "id": 1,
    "its_id": "30361286",
    "mcd_id": 5,
    "is_cleared": true,
    "cleared_by_its": "30360001",
    "cleared_at": "2026-01-30T10:00:00.000000Z",
    "notes": null,
    "created_at": "2026-01-30T09:00:00.000000Z",
    "updated_at": "2026-01-30T10:00:00.000000Z"
  }
}
```

**Business logic:**

1. Validate `miqaat_id` and that `mcd_id` exists in `miqaat_check_definitions` with `miqaat_id` = path `miqaat_id`.
2. If `is_cleared` is true, set `cleared_at` to current timestamp; otherwise set to null (and optionally clear `cleared_by_its`).
3. Upsert `miqaat_checks`: if a row with `(its_id, mcd_id)` exists, update `is_cleared`, `cleared_by_its`, `cleared_at`, `notes`, `updated_at`; otherwise insert a new row with these fields and set `created_at`/`updated_at`.

**Error responses:**

- **404** – Miqaat or definition not found: `{ "success": false, "error": "NOT_FOUND", "message": "Miqaat or check definition not found." }`
- **422** – Validation error (e.g. missing `its_id`, `mcd_id`, or `is_cleared`): `{ "success": false, "error": "VALIDATION_ERROR", "message": "..." }`

---

## Summary Table

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 1 | GET | `/miqaat-check-definitions?user_type=Anjuman&...` | Optional: filter definitions by user type (if not already supported). |
| 2 | GET | `/miqaats/{miqaat_id}/miqaat-checks?its_id={its_id}` | List all miqaat_checks for a person in a miqaat. |
| 3 | PUT or POST | `/miqaats/{miqaat_id}/miqaat-checks` | Upsert one miqaat_check (clear/unclear). |

---

## Database Reference

- **miqaat_checks** (from `miqaat_api.sql`): `id`, `its_id`, `mcd_id`, `is_cleared`, `cleared_by_its`, `cleared_at`, `notes`, `created_at`, `updated_at`. Unique on `(its_id, mcd_id)`.
- **miqaat_check_definitions**: `mcd_id`, `name`, `miqaat_id`, `user_type`, ...

Frontend will: (1) list Anjuman definitions for the selected miqaat (`GET /miqaat-check-definitions?miqaat_id=X&user_type=Anjuman`), (2) list checks for that person in that miqaat (`GET /miqaats/{miqaat_id}/miqaat-checks?its_id=Y`), (3) merge to show each definition with cleared/uncleared, (4) on toggle call the upsert endpoint above.
