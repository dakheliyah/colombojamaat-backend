# Sharaf API Documentation

This document provides comprehensive API documentation for all endpoints related to the `sharafs` table.

## Base URL
All endpoints are prefixed with your API base URL (e.g., `/api` or `/`).

---

## 1. Create Sharaf

**Endpoint:** `POST /sharafs`

**Description:** Creates a new sharaf record.

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_definition_id` | integer | Yes | ID of the sharaf definition (must exist in `sharaf_definitions` table) |
| `rank` | integer | Yes | Rank of the sharaf (must be unique within the sharaf_definition_id, minimum: 1) |
| `name` | string | No | Name of the sharaf (max 255 characters) |
| `capacity` | integer | Yes | Maximum number of people in the sharaf (minimum: 1) |
| `status` | string | No | Status of the sharaf. Options: `pending`, `bs_approved`, `confirmed`, `rejected`, `cancelled`. Default: `pending` |
| `hof_its` | string | Yes | ITS number of the Head of Family |
| `lagat_paid` | boolean | No | Whether lagat payment is paid. Default: `false` |
| `najwa_ada_paid` | boolean | No | Whether najwa ada payment is paid. Default: `false` |

### Request Example

```json
{
  "sharaf_definition_id": 1,
  "rank": 1,
  "name": "Sharaf Group 1",
  "capacity": 10,
  "status": "pending",
  "hof_its": "123456",
  "lagat_paid": false,
  "najwa_ada_paid": false
}
```

### Success Response (201 Created)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "sharaf_definition_id": 1,
    "rank": 1,
    "name": "Sharaf Group 1",
    "capacity": 10,
    "status": "pending",
    "hof_its": "123456",
    "lagat_paid": false,
    "najwa_ada_paid": false,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "sharaf_definition": {
      "id": 1,
      "event_id": 1,
      "name": "Opening Ceremony Sharaf",
      "description": "Sharaf positions for opening ceremony.",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "sharaf_members": [],
    "sharaf_clearances": []
  }
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "A sharaf with this rank already exists for the given sharaf definition."
}
```

---

## 2. Get Sharaf

**Endpoint:** `GET /sharafs/{sharaf_id}`

**Description:** Retrieves a single sharaf record with its related data.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "sharaf_definition_id": 1,
    "rank": 1,
    "name": "Sharaf Group 1",
    "capacity": 10,
    "status": "pending",
    "hof_its": "123456",
    "lagat_paid": false,
    "najwa_ada_paid": false,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "sharaf_definition": {
      "id": 1,
      "event_id": 1,
      "name": "Opening Ceremony Sharaf",
      "description": "Sharaf positions for opening ceremony.",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "sharaf_members": [
      {
        "id": 1,
        "sharaf_id": 1,
        "sharaf_position_id": 1,
        "its_id": "123456",
        "sp_keyno": 1,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z",
        "sharaf_position": {
          "id": 1,
          "sharaf_definition_id": 1,
          "name": "Head of Family",
          "description": "Head of Family position",
          "created_at": "2024-01-01T00:00:00.000000Z",
          "updated_at": "2024-01-01T00:00:00.000000Z"
        }
      }
    ],
    "sharaf_clearances": [
      {
        "id": 1,
        "sharaf_id": 1,
        "hof_its": "123456",
        "is_cleared": true,
        "cleared_by_its": "789012",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
      }
    ]
  }
}
```

### Error Response (404 Not Found)

```json
{
  "message": "No query results for model [App\\Models\\Sharaf] 1"
}
```

---

## 3. Delete Sharaf

**Endpoint:** `DELETE /sharafs/{sharaf_id}`

**Description:** Deletes a sharaf record and all related data (members, clearances) due to cascade delete.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf to delete |

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Error Response (404 Not Found)

```json
{
  "message": "No query results for model [App\\Models\\Sharaf] 1"
}
```

---

## 4. Update Sharaf Status

**Endpoint:** `PUT /sharafs/{sharaf_id}/status` or `PATCH /sharafs/{sharaf_id}/status`

**Description:** Updates the status of a sharaf.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | Yes | New status. Options: `pending`, `bs_approved`, `confirmed`, `rejected`, `cancelled` |

### Request Example

```json
{
  "status": "bs_approved"
}
```

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The status field is required."
}
```

---

## 5. Evaluate Sharaf Confirmation

**Endpoint:** `POST /sharafs/{sharaf_id}/evaluate-confirmation`

**Description:** Evaluates and updates the confirmation status of a sharaf based on clearance and payment status.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Error Response (404 Not Found)

```json
{
  "message": "No query results for model [App\\Models\\Sharaf] 1"
}
```

---

## 6. Get Sharaf Members

**Endpoint:** `GET /sharafs/{sharaf_id}/members`

**Description:** Retrieves all members assigned to a sharaf.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sharaf_id": 1,
      "sharaf_position_id": 1,
      "its_id": "123456",
      "sp_keyno": 1,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z",
      "sharaf_position": {
        "id": 1,
        "sharaf_definition_id": 1,
        "name": "Head of Family",
        "description": "Head of Family position",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
      }
    }
  ]
}
```

---

## 7. Add Sharaf Member

**Endpoint:** `POST /sharafs/{sharaf_id}/members`

**Description:** Adds a member to a sharaf at a specific position.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `its` | numeric | Yes | ITS number of the person to add |
| `position_id` | integer | Yes | ID of the sharaf position (must exist in `sharaf_positions` table) |
| `sp_keyno` | integer | No | Key number for ordering within the position |

### Request Example

```json
{
  "its": "123456",
  "position_id": 1,
  "sp_keyno": 1
}
```

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Success Response with Warnings (200 OK)

If the person is already allocated to another sharaf, a warning is returned:

```json
{
  "success": true,
  "warnings": [
    {
      "type": "ALREADY_ALLOCATED",
      "message": "This person is already allocated to another sharaf.",
      "sharaf_ids": [2, 3]
    }
  ]
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The its field is required."
}
```

---

## 8. Remove Sharaf Member

**Endpoint:** `DELETE /sharafs/{sharaf_id}/members/{its}`

**Description:** Removes a member from a sharaf.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |
| `its` | string | Yes | ITS number of the person to remove |

### Success Response (200 OK)

```json
{
  "success": true
}
```

---

## 9. Toggle Sharaf Clearance

**Endpoint:** `POST /sharafs/{sharaf_id}/clearances`

**Description:** Toggles the clearance status for a sharaf's Head of Family.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `is_cleared` | boolean | Yes | Whether the clearance is complete |
| `cleared_by_its` | string | No | ITS number of the person who cleared it |

### Request Example

```json
{
  "is_cleared": true,
  "cleared_by_its": "789012"
}
```

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The is cleared field is required."
}
```

---

## 10. Toggle Lagat Payment

**Endpoint:** `POST /sharafs/{sharaf_id}/lagat`

**Description:** Toggles the lagat payment status for a sharaf.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `paid` | boolean | Yes | Whether lagat payment is paid |

### Request Example

```json
{
  "paid": true
}
```

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The paid field is required."
}
```

---

## 11. Toggle Najwa Ada Payment

**Endpoint:** `POST /sharafs/{sharaf_id}/najwa`

**Description:** Toggles the najwa ada payment status for a sharaf.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sharaf_id` | integer | Yes | ID of the sharaf |

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `paid` | boolean | Yes | Whether najwa ada payment is paid |

### Request Example

```json
{
  "paid": true
}
```

### Success Response (200 OK)

```json
{
  "success": true
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The paid field is required."
}
```

---

## Common Response Formats

### Success Response
All successful responses follow this format:
```json
{
  "success": true,
  "data": { ... }  // Present when data is returned
}
```

### Error Response
All error responses follow this format:
```json
{
  "error": "ERROR_CODE",
  "message": "Human-readable error message"
}
```

### Validation Error Response
Validation errors return a 422 status code:
```json
{
  "error": "VALIDATION_ERROR",
  "message": "The field name is required."
}
```

### Not Found Error Response
Not found errors return a 404 status code:
```json
{
  "message": "No query results for model [App\\Models\\Sharaf] {id}"
}
```

---

## Status Values

The `status` field can have the following values:
- `pending` - Sharaf is pending approval
- `bs_approved` - Sharaf has been approved by BS
- `confirmed` - Sharaf is confirmed (requires clearance and payments)
- `rejected` - Sharaf has been rejected
- `cancelled` - Sharaf has been cancelled

---

## Notes

1. **Cascade Deletes**: When a sharaf is deleted, all related sharaf members and clearances are automatically deleted due to database cascade constraints.

2. **Rank Uniqueness**: The `rank` field must be unique within a `sharaf_definition_id`. Attempting to create a sharaf with a duplicate rank will result in a validation error.

3. **Member Allocation**: When adding a member, if the person is already allocated to another sharaf, the operation will succeed but return a warning with the sharaf IDs where the person is already allocated.

4. **Confirmation Requirements**: A sharaf can only be confirmed when:
   - Clearance for the sharaf's HOF is complete (`is_cleared = true`)
   - `lagat_paid = true`
   - `najwa_ada_paid = true`

5. **ITS Format**: ITS numbers are stored as strings to preserve leading zeros and handle various formats.
