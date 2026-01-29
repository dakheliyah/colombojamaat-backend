# Miqaat Check Definitions API Documentation

This document describes the CRUD API for the `miqaat_check_definitions` table. Each definition belongs to one **miqaat** (foreign key `miqaat_id`) and has a **name** that must be unique within that miqaat. Check definitions are used when recording miqaat checks; the `miqaat_checks` table no longer stores `miqaat_id`—the miqaat is determined by the check’s definition.

## Base URL

All endpoints are prefixed with your API base URL (e.g. `/api` or `/`).

---

## 1. List Miqaat Check Definitions

**Endpoint:** `GET /miqaat-check-definitions`

**Description:** Returns a paginated list of miqaat check definitions, ordered by miqaat_id then name. Optionally filter by miqaat.

### Query Parameters

| Parameter   | Type    | Required | Description                                      |
|------------|---------|----------|--------------------------------------------------|
| `miqaat_id`| integer | No       | Filter by miqaat (must exist in `miqaats` table) |
| `page`     | integer | No       | Page number (default: 1)                         |
| `per_page` | integer | No       | Items per page, 1–100 (default: 15)             |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "mcd_id": 1,
        "name": "Security",
        "miqaat_id": 1,
        "created_at": "2026-01-29T12:00:00.000000Z",
        "updated_at": "2026-01-29T12:00:00.000000Z",
        "miqaat": {
          "id": 1,
          "name": "Miqaat 2026",
          "start_date": "2026-01-01",
          "end_date": "2026-01-31",
          "description": null,
          "created_at": "2026-01-29T12:00:00.000000Z",
          "updated_at": "2026-01-29T12:00:00.000000Z"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 5,
      "last_page": 1,
      "from": 1,
      "to": 5
    }
  }
}
```

---

## 2. Get Single Miqaat Check Definition

**Endpoint:** `GET /miqaat-check-definitions/{mcd_id}`

**Description:** Returns one miqaat check definition by ID, including the related miqaat.

### URL Parameters

| Parameter | Type    | Required | Description                          |
|-----------|---------|----------|--------------------------------------|
| `mcd_id`  | integer | Yes      | Primary key of the check definition  |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "mcd_id": 1,
    "name": "Security",
    "miqaat_id": 1,
    "created_at": "2026-01-29T12:00:00.000000Z",
    "updated_at": "2026-01-29T12:00:00.000000Z",
    "miqaat": {
      "id": 1,
      "name": "Miqaat 2026",
      "start_date": "2026-01-01",
      "end_date": "2026-01-31",
      "description": null,
      "created_at": "2026-01-29T12:00:00.000000Z",
      "updated_at": "2026-01-29T12:00:00.000000Z"
    }
  }
}
```

### Error Response (404 Not Found)

```json
{
  "success": false,
  "error": "NOT_FOUND",
  "message": "Miqaat check definition not found."
}
```

---

## 3. Create Miqaat Check Definition

**Endpoint:** `POST /miqaat-check-definitions`

**Description:** Creates a new miqaat check definition. The `name` must be unique **within the given miqaat** (same name is allowed for different miqaats).

### Request Body

| Parameter   | Type   | Required | Description                              |
|-------------|--------|----------|------------------------------------------|
| `miqaat_id` | integer| Yes      | ID of the miqaat (must exist in `miqaats`) |
| `name`      | string | Yes      | Name (max 255 characters, unique per miqaat) |

### Request Example

```json
{
  "miqaat_id": 1,
  "name": "Security"
}
```

### Success Response (201 Created)

```json
{
  "success": true,
  "data": {
    "mcd_id": 1,
    "name": "Security",
    "miqaat_id": 1,
    "created_at": "2026-01-29T12:00:00.000000Z",
    "updated_at": "2026-01-29T12:00:00.000000Z",
    "miqaat": {
      "id": 1,
      "name": "Miqaat 2026",
      "start_date": "2026-01-01",
      "end_date": "2026-01-31",
      "description": null,
      "created_at": "2026-01-29T12:00:00.000000Z",
      "updated_at": "2026-01-29T12:00:00.000000Z"
    }
  }
}
```

### Error Response (422 Unprocessable Entity)

**Validation error (e.g. missing or invalid fields):**

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "The miqaat id field is required."
}
```

**Duplicate name for the same miqaat:**

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "A check definition with this name already exists for this miqaat."
}
```

---

## 4. Update Miqaat Check Definition

**Endpoint:** `PUT /miqaat-check-definitions/{mcd_id}` or `PATCH /miqaat-check-definitions/{mcd_id}`

**Description:** Updates an existing miqaat check definition. The `name` must remain unique within the given miqaat (excluding the current record).

### URL Parameters

| Parameter | Type    | Required | Description                          |
|-----------|---------|----------|--------------------------------------|
| `mcd_id`  | integer | Yes      | Primary key of the check definition  |

### Request Body

| Parameter   | Type   | Required | Description                              |
|-------------|--------|----------|------------------------------------------|
| `miqaat_id`| integer| Yes      | ID of the miqaat (must exist in `miqaats`) |
| `name`     | string | Yes      | Name (max 255 characters, unique per miqaat) |

### Request Example

```json
{
  "miqaat_id": 1,
  "name": "Security & Access"
}
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "mcd_id": 1,
    "name": "Security & Access",
    "miqaat_id": 1,
    "created_at": "2026-01-29T12:00:00.000000Z",
    "updated_at": "2026-01-29T14:30:00.000000Z",
    "miqaat": {
      "id": 1,
      "name": "Miqaat 2026",
      "start_date": "2026-01-01",
      "end_date": "2026-01-31",
      "description": null,
      "created_at": "2026-01-29T12:00:00.000000Z",
      "updated_at": "2026-01-29T12:00:00.000000Z"
    }
  }
}
```

### Error Response (404 Not Found)

```json
{
  "success": false,
  "error": "NOT_FOUND",
  "message": "Miqaat check definition not found."
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "A check definition with this name already exists for this miqaat."
}
```

---

## 5. Delete Miqaat Check Definition

**Endpoint:** `DELETE /miqaat-check-definitions/{mcd_id}`

**Description:** Deletes a miqaat check definition. If any `miqaat_checks` reference this definition via `mcd_id`, their `mcd_id` is set to `null` (foreign key uses `nullOnDelete`).

### URL Parameters

| Parameter | Type    | Required | Description                          |
|-----------|---------|----------|--------------------------------------|
| `mcd_id`  | integer | Yes      | Primary key of the check definition  |

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Error Response (404 Not Found)

```json
{
  "success": false,
  "error": "NOT_FOUND",
  "message": "Miqaat check definition not found."
}
```

---

## Summary Table

| Method    | Endpoint                              | Description              |
|-----------|----------------------------------------|--------------------------|
| GET       | `/miqaat-check-definitions`            | List (paginated, optional `miqaat_id` filter) |
| GET       | `/miqaat-check-definitions/{mcd_id}`   | Get one by ID            |
| POST      | `/miqaat-check-definitions`            | Create                   |
| PUT/PATCH | `/miqaat-check-definitions/{mcd_id}`   | Update                   |
| DELETE    | `/miqaat-check-definitions/{mcd_id}`   | Delete                   |

## Data Model

### miqaat_check_definitions

| Field        | Type     | Description                              |
|--------------|----------|------------------------------------------|
| `mcd_id`     | integer  | Primary key                              |
| `name`       | string   | Name (max 255), unique per miqaat        |
| `miqaat_id`  | integer  | Foreign key to `miqaats.id`              |
| `created_at` | datetime | Set on create                            |
| `updated_at` | datetime | Set on create/update                     |

### miqaat_checks (reference only)

The `miqaat_checks` table **no longer has a `miqaat_id`** column. The miqaat for a check is determined by its definition: `miqaat_checks.mcd_id` → `miqaat_check_definitions.miqaat_id`. Uniqueness is on `(its_id, mcd_id)`.

| Field         | Type     | Description                              |
|---------------|----------|------------------------------------------|
| `id`          | integer  | Primary key                              |
| `its_id`      | string   | Reference to census / person              |
| `mcd_id`      | integer  | Foreign key to `miqaat_check_definitions.mcd_id` (miqaat implied) |
| `is_cleared`  | boolean  | Whether the check is cleared              |
| `cleared_by_its` | string | ITS of user who cleared (optional)       |
| `cleared_at`  | datetime | When cleared (optional)                  |
| `notes`       | text     | Optional notes                           |
| `created_at`  | datetime | Set on create                            |
| `updated_at`  | datetime | Set on create/update                     |
