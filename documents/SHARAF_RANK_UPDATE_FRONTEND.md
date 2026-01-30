# Sharaf Rank Update — Frontend Integration Guide

This document describes how the frontend should call the Sharaf update API when changing ranks, and how to handle the response.

---

## Endpoint

`PATCH /api/sharafs/{sharaf_id}`

---

## Request Format

Send a JSON body with the fields you want to update. For rank changes, include only `rank` (or include other fields as needed):

```json
{
  "rank": 1
}
```

### Rank behavior

| `rank` value | API behavior |
|--------------|--------------|
| **`0`** | Assigns the next available rank (append to end). Same as `max(rank) + 1`. |
| **`N` (free)** | Sets this sharaf’s rank to `N` if no other sharaf has it. |
| **`N` (taken)** | Swaps ranks: this sharaf gets `N`, the sharaf that had `N` gets this sharaf’s old rank. |

---

## Request examples

### Move to a specific rank (or swap with occupant)

```json
{
  "rank": 3
}
```

### Append to end (assign next rank)

```json
{
  "rank": 0
}
```

### Update rank and other fields together

```json
{
  "rank": 2,
  "capacity": 15,
  "comments": "Updated after reorder"
}
```

---

## Success response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 12,
    "sharaf_definition_id": 5,
    "rank": 3,
    "capacity": 10,
    "status": "pending",
    "hof_its": "123456",
    "token": null,
    "comments": null,
    "created_at": "2026-01-30T12:00:00.000000Z",
    "updated_at": "2026-01-30T21:15:00.000000Z",
    "hof_name": "Family Name",
    "sharaf_definition": { ... },
    "sharaf_members": [ ... ],
    "sharaf_clearances": [ ... ],
    "sharaf_payments": [ ... ]
  }
}
```

---

## Error responses

### 404 Not Found — Sharaf does not exist

```json
{
  "message": "No query results for model [App\\Models\\Sharaf] 12"
}
```

### 422 Unprocessable Entity — Validation error

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "A sharaf with this rank already exists for the given sharaf definition."
}
```

(Should be rare with the new swap logic; this can occur under race conditions.)

---

## Frontend implementation notes

1. **Pass only `rank` when reordering**
   - To change rank only, send `{"rank": N}`.
   - Use `rank: 0` to append to the end.

2. **Use the updated sharaf from the response**
   - On success, replace the local sharaf with `response.data`.
   - If the list is ordered by `rank`, re-sort or re-fetch after a successful update.

3. **When a rank is swapped**
   - The sharaf that previously had rank `N` now has the updated sharaf’s old rank.
   - Refetch the list (e.g. `GET /sharafs?sharaf_definition_id=X`) to get correct ordering, or update both sharafs locally from the response if your UI needs immediate consistency.

4. **Use JSON and correct Content-Type**
   - Send the body as JSON.
   - Header: `Content-Type: application/json`.

5. **Example fetch (JavaScript)**

```javascript
async function updateSharafRank(sharafId, newRank) {
  const response = await fetch(`/api/sharafs/${sharafId}`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      // Add auth header if required
      // 'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ rank: newRank }),
  });

  if (!response.ok) {
    const err = await response.json();
    throw new Error(err.message || 'Update failed');
  }

  return response.json();
}
```
