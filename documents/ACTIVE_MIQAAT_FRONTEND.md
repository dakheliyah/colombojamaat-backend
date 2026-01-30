# Active Miqaat — Frontend Integration Guide

This document describes how the frontend should work with the “active miqaat” concept: only one miqaat is active at a time, and all data-reading APIs return or accept only the active miqaat.

---

## Concept

- Only **one miqaat** can be “active” at a time (`active_status = true`).
- All APIs that **fetch** data (events, wajebaat, miqaat checks, sharafs, sharaf payments, etc.) are scoped to the active miqaat.
- For endpoints that take `miqaat_id` in the URL or body, the backend **requires** that value to be the active miqaat. If it is not, the API returns **403 Forbidden** with error code `MIQAAT_NOT_ACTIVE`.

---

## Getting the current miqaat

### Option 1: GET /api/miqaats/active (recommended)

Returns the single active miqaat object. Use this when you need the current miqaat id to build URLs (e.g. `/miqaats/{id}/wajebaat`, `/events/{id}`).

- **Endpoint:** `GET /api/miqaats/active`
- **Success (200):** `{ "success": true, "data": { "id": 1, "name": "...", "active_status": true, ... } }`
- **Not found (404):** No miqaat is currently active (e.g. none set yet). Handle by prompting the admin to set an active miqaat via PATCH below.

### Option 2: GET /api/miqaats

Returns **all** miqaats. Each item includes `active_status` (boolean). The one with `active_status === true` is the current miqaat.

- **Endpoint:** `GET /api/miqaats`
- **Success (200):** `{ "success": true, "data": [ { "id": 1, "name": "...", "active_status": true }, { "id": 2, "name": "...", "active_status": false }, ... ] }`

Use this when you need to show a list of miqaats (e.g. admin selector) and also know which is current.

---

## Using miqaat_id in URLs

For routes that include `miqaat_id` in the path, the backend **requires** that value to be the **active** miqaat:

- `GET /api/events/{miqaat_id}`
- `GET /api/miqaats/{miqaat_id}/miqaat-checks`
- `PUT` / `POST /api/miqaats/{miqaat_id}/miqaat-checks`
- `GET /api/miqaats/{miqaat_id}/wajebaat`
- `GET /api/miqaats/{miqaat_id}/wajebaat/{its_id}`
- `GET /api/miqaats/{miqaat_id}/wajebaat-categories`
- `GET /api/miqaats/{miqaat_id}/wajebaat-groups`
- …and other wajebaat/group endpoints with `{miqaat_id}`

**Recommendation:** Always resolve the current miqaat id via `GET /api/miqaats/active` (or from `GET /api/miqaats` by finding the item with `active_status === true`), then use that `id` in the above URLs. Do **not** hardcode or persist an arbitrary miqaat id; it may no longer be active and will result in 403.

If the user selects a **different** miqaat (e.g. from a dropdown):

1. **Option A:** Call `PATCH /api/miqaats/{id}` with `{ "active_status": true }` to switch the active miqaat, then refetch data and rebuild URLs with the new id.
2. **Option B:** Show a message that “Data is only available for the current miqaat” and offer a “Switch to this miqaat” action that does the PATCH above.

---

## Switching the active miqaat

- **Endpoint:** `PATCH /api/miqaats/{id}` (or `PUT /api/miqaats/{id}`)
- **Body:** `{ "active_status": true }`
- **Effect:** This miqaat becomes active; all other miqaats are set to `active_status: false`. Only one miqaat can be active at a time.

You can also update other fields in the same request (e.g. `name`, `start_date`, `end_date`, `description`).

**Example:**

```json
PATCH /api/miqaats/2
{ "active_status": true }
```

After a successful response, all list/detail views that depend on “current miqaat” should refetch using the new active miqaat id (e.g. call `GET /api/miqaats/active` again and then reload events, wajebaat, etc.).

---

## List endpoints that are scoped to the active miqaat (no miqaat_id in URL)

These endpoints **do not** take `miqaat_id` in the path; the backend automatically returns only data for the active miqaat:

- **GET /api/events** — Returns only events for the active miqaat.
- **GET /api/sharafs** — Returns only sharafs whose event belongs to the active miqaat.
- **GET /api/sharaf-payments** — Returns only payments for sharafs that belong to the active miqaat.
- **GET /api/miqaat-check-definitions** — When the query parameter `miqaat_id` is **omitted**, results are limited to the active miqaat. When `miqaat_id` is **provided**, it must be the active miqaat (403 otherwise).

So the frontend can call these without passing a miqaat id; the server uses the active one.

---

## Handling 403 MIQAAT_NOT_ACTIVE

When any endpoint that takes `miqaat_id` returns **403** with error code `MIQAAT_NOT_ACTIVE`:

- **Meaning:** The `miqaat_id` sent (in URL or body) is not the current active miqaat.
- **Suggested UI:**  
  - Show a short message: “This data is for the current miqaat only.”  
  - Offer to “Switch to current miqaat” (call `GET /api/miqaats/active` and then refetch) or “Switch to another miqaat” (show miqaat list and call PATCH to set that one active, then refetch).

---

## UI implications summary

1. **Default all list/detail views** to the active miqaat: resolve it once via `GET /api/miqaats/active` (or from `GET /api/miqaats` using `active_status`), then use that id in all URLs that require `miqaat_id`.
2. **Miqaat selector:**  
   - If the user picks a non-active miqaat, either (a) call PATCH to set that miqaat active and refetch, or (b) show a message that only the current miqaat’s data is available and offer “Switch to this miqaat”.
3. **403 MIQAAT_NOT_ACTIVE:** Prompt the user to switch to the active miqaat or refresh after switching.
4. **No active miqaat (404 on GET /api/miqaats/active):** Prompt the admin to set an active miqaat via `PATCH /api/miqaats/{id}` with `{ "active_status": true }`.
