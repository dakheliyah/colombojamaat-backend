# Payment Definition user_type — Frontend Integration Guide

This document describes the addition of the `user_type` field to payment definitions so the frontend can display and edit it, and optionally filter or group by department (e.g. Finance).

---

## Summary of change

- **Backend:** A new `user_type` text column was added to the `payment_definitions` table.
- **Default:** All existing payment definitions were assigned `user_type = 'Finance'`. New payment definitions default to `'Finance'` if `user_type` is not sent.
- **Purpose:** Allows grouping or filtering payment definitions by department/user type (e.g. Finance, Admin) in the UI.

---

## API changes

### List payment definitions

**Endpoints:**  
`GET /api/payment-definitions`  
`GET /api/sharaf-definitions/{id}/payment-definitions`

**Response:** Each payment definition object now includes `user_type` (string):

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sharaf_definition_id": 5,
      "name": "lagat",
      "description": "Lagat payment",
      "user_type": "Finance",
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

- **Pagination:** For `GET /api/payment-definitions`, the response shape is `{ "data": [...], "pagination": { ... } }` as before; each item in `data` includes `user_type`.
- **Filtering by user_type:** The API does not currently support a `user_type` query parameter. You can filter the list client-side by `user_type` if needed.

---

### Create payment definition

**Endpoint:** `POST /api/payment-definitions`

**Request body:** `user_type` is **optional**. If omitted, the backend sets it to `'Finance'`.

| Field                 | Type   | Required | Notes                                      |
|-----------------------|--------|----------|--------------------------------------------|
| `sharaf_definition_id` | integer | Yes      | As before.                                 |
| `name`               | string | Yes      | As before.                                 |
| `description`        | string | No       | As before.                                 |
| `user_type`          | string | No       | Department/user type; defaults to `Finance`. |

**Example with user_type:**

```json
{
  "sharaf_definition_id": 5,
  "name": "hadiyat",
  "description": "Hadiyat payment",
  "user_type": "Finance"
}
```

**Example without user_type (defaults to Finance):**

```json
{
  "sharaf_definition_id": 5,
  "name": "najwa_ada",
  "description": "Najwa ada payment"
}
```

**Success (201):** Response `data` includes the created payment definition with `user_type` (either the value sent or `"Finance"`).

---

### Update payment definition

**Endpoints:** `PUT /api/payment-definitions/{id}` or `PATCH /api/payment-definitions/{id}`

**Request body:** All fields are optional (partial update). Include only the fields you want to change.

| Field                 | Type   | Required | Notes                |
|-----------------------|--------|----------|----------------------|
| `sharaf_definition_id` | integer | No       | As before.           |
| `name`               | string | No       | As before.           |
| `description`        | string | No       | As before.           |
| `user_type`          | string | No       | New; department/user type. |

**Example — change user_type only:**

```json
{
  "user_type": "Admin"
}
```

**Success (200):** Response `data` is the updated payment definition including `user_type`.

---

## TypeScript / interface updates

Add `user_type` to your `PaymentDefinition` interface (and to any DTOs or API response types):

```typescript
interface PaymentDefinition {
  id: number;
  sharaf_definition_id: number;
  name: string;
  description: string | null;
  user_type: string;  // e.g. "Finance", "Admin"
  created_at?: string;
  updated_at?: string;
}
```

For **create** and **update** request bodies:

```typescript
interface PaymentDefinitionCreateInput {
  sharaf_definition_id: number;
  name: string;
  description?: string | null;
  user_type?: string;  // optional; defaults to "Finance" on backend
}

interface PaymentDefinitionUpdateInput {
  sharaf_definition_id?: number;
  name?: string;
  description?: string | null;
  user_type?: string;
}
```

---

## UI action items

Use this checklist to integrate `user_type` in the frontend.

### Data and types

- [ ] Add `user_type` to `PaymentDefinition` (and related) TypeScript interfaces.
- [ ] Ensure create/update request types include optional `user_type` where applicable.

### List and display

- [ ] Show `user_type` in payment definition list/detail (e.g. column, badge, or secondary text).
- [ ] Optionally filter or group payment definitions by `user_type` (client-side from list response).
- [ ] If you embed payment definitions in sharaf/sharaf-definition views, include `user_type` in the displayed payload if relevant.

### Create payment definition form

- [ ] Add an optional “User type” / “Department” field (e.g. text input or dropdown).
- [ ] Default the field to `"Finance"` when creating, or leave empty to rely on backend default.
- [ ] Send `user_type` in `POST /api/payment-definitions` when the user has set it.

### Edit payment definition form

- [ ] Add “User type” / “Department” to the edit form; load current `user_type` from the definition.
- [ ] On save, send `user_type` in `PUT` or `PATCH /api/payment-definitions/{id}` when the user has changed it.

### Consistency

- [ ] If you have a fixed set of departments (e.g. Finance, Admin), consider a dropdown or autocomplete and use the same values when creating/updating.
- [ ] Existing definitions all have `user_type === 'Finance'`; new ones default to `'Finance'` if not specified.

---

## Backward compatibility

- **Existing API consumers:** If the frontend does not send `user_type` on create, the backend still creates the record with `user_type = 'Finance'`. No change required for create to keep working.
- **Existing data:** All existing payment definitions have been assigned `user_type = 'Finance'`. No migration is needed on the frontend for old data.
- **List/show responses:** All payment definition responses now include `user_type`. Ensure your types and UI can handle the new field (adding it to interfaces and optionally displaying it is enough).

---

## Quick reference

| API | Change |
|-----|--------|
| `GET /api/payment-definitions` | Response items include `user_type`. |
| `GET /api/sharaf-definitions/{id}/payment-definitions` | Response items include `user_type`. |
| `POST /api/payment-definitions` | Optional body field `user_type`; default `"Finance"`. |
| `PUT /api/payment-definitions/{id}` | Optional body field `user_type`. |
| `PATCH /api/payment-definitions/{id}` | Optional body field `user_type`. |

**Document version:** January 2026 (payment definition user_type rollout)
