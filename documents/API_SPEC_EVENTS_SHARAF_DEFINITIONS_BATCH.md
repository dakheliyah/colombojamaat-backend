# API Spec: Get Event Sharaf Definitions (with optional nested data)

**Purpose:** Single endpoint to load sharaf definitions for an event, with optional nested `positions` and/or `payment_definitions`, to avoid N+1 requests on the Create Sharaf page.

**Related:** See `CREATE_SHARAF_API_AUDIT.md` for context.

---

## Endpoint

| Method | Path |
|--------|------|
| `GET` | `/api/events/{eventId}/sharaf-definitions` |

*(Base URL and version prefix are per existing API; path is relative to API root.)*

---

## Path parameters

| Name     | Type   | Required | Description |
|----------|--------|----------|-------------|
| `eventId` | integer | Yes | Event ID. Returns 404 if event does not exist. |

---

## Query parameters

| Name     | Type   | Required | Description |
|----------|--------|----------|-------------|
| `include` | string | No | Comma-separated list of relations to embed in each sharaf definition. Allowed values: `positions`, `payment-definitions`. If omitted or empty, response is the same as the current behaviour (definitions only, no nested data). |

**Examples:**
- `?include=positions` — each definition includes a `positions` array.
- `?include=payment-definitions` — each definition includes a `payment_definitions` array.
- `?include=positions,payment-definitions` — each definition includes both.

---

## Request

**Example (with include):**
```http
GET /api/events/1/sharaf-definitions?include=positions,payment-definitions
```

**Example (no include, backward compatible):**
```http
GET /api/events/1/sharaf-definitions
```

No request body.

---

## Response

### Success (200 OK)

**Without `include` (or empty):**  
Same shape as existing `GET /events/{eventId}/sharaf-definitions` — array of sharaf definitions with no nested relations.

**With `include=positions` and/or `include=payment-definitions`:**  
Same array of sharaf definitions, with extra fields populated as requested.

**Response body (wrapped format, if your API uses it):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "event_id": 1,
      "name": "Nikah",
      "key": null,
      "description": null,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "positions": [...],
      "payment_definitions": [...]
    }
  ]
}
```

**Or (if your API returns a bare array):**
```json
[
  {
    "id": 1,
    "event_id": 1,
    "name": "Nikah",
    "key": null,
    "description": null,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "positions": [...],
    "payment_definitions": [...]
  }
]
```

*(Backend should match the existing response style for `GET /events/{eventId}/sharaf-definitions` for consistency.)*

---

## Response types (nested payloads)

### SharafDefinition (base, always present)

| Field         | Type     | Description |
|---------------|----------|-------------|
| `id`          | integer  | Sharaf definition ID. |
| `event_id`    | integer  | Event ID. |
| `name`        | string   | Definition name (e.g. "Nikah", "Ziyafat"). |
| `key`         | string \| null | Optional key. |
| `description` | string \| null | Optional description. |
| `created_at`  | string   | ISO 8601 datetime. |
| `updated_at`  | string   | ISO 8601 datetime. |
| `positions`   | array    | **Only when `include` contains `positions`.** Array of `SharafPosition`. |
| `payment_definitions` | array | **Only when `include` contains `payment-definitions`.** Array of `PaymentDefinition`. |

### SharafPosition (when `include=positions`)

| Field                | Type           | Description |
|----------------------|----------------|-------------|
| `id`                 | integer        | Position ID. |
| `sharaf_definition_id` | integer      | Sharaf definition ID. |
| `name`               | string         | Internal name. |
| `display_name`       | string         | Display name. |
| `capacity`           | integer \| null | Capacity if applicable. |
| `order`              | integer        | Sort order (e.g. 1 = HOF). |
| `created_at`         | string         | ISO 8601 datetime. |
| `updated_at`         | string         | ISO 8601 datetime. |

### PaymentDefinition (when `include=payment-definitions`)

| Field                | Type     | Description |
|----------------------|----------|-------------|
| `id`                 | integer  | Payment definition ID. |
| `sharaf_definition_id` | integer | Sharaf definition ID. |
| `name`               | string   | Name (e.g. "Hadiyat", "Misaaq"). |
| `description`        | string \| null | Optional description. |
| `user_type`          | string (optional) | e.g. "Finance", "Admin". |
| `created_at`         | string (optional) | ISO 8601 datetime. |
| `updated_at`         | string (optional) | ISO 8601 datetime. |

---

## Error responses

| Status | When |
|--------|------|
| `404 Not Found` | No event with the given `eventId`. |
| `400 Bad Request` | Invalid `eventId` or invalid value in `include` (e.g. typo or unsupported relation). |

---

## Backward compatibility

- **No `include` or empty `include`:** Response must be identical to the current `GET /events/{eventId}/sharaf-definitions` (no `positions` or `payment_definitions` on each item). Existing clients are unaffected.
- **New clients** can call the same path with `?include=positions,payment-definitions` and use the nested arrays to avoid extra calls to:
  - `GET /sharaf-definitions/{id}/positions`
  - `GET /sharaf-definitions/{id}/payment-definitions`

---

## Frontend usage (after backend is ready)

The frontend will:

1. Call `GET /events/{eventId}/sharaf-definitions?include=positions,payment-definitions` when loading the Create Sharaf form for that event.
2. Use the returned `data` (or array) to populate:
   - `sharafDefinitions`
   - `definitionPositions` (from each item’s `positions`)
   - `definitionPaymentDefinitions` (from each item’s `payment_definitions`)
3. Avoid separate requests for positions and payment-definitions per definition, reducing initial load from many requests to one.
