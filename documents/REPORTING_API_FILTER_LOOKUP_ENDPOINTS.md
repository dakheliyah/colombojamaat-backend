# Report Builder Filter Lookup APIs

## Overview
The Report Builder filter system requires lookup data for ID-based filter fields to display user-friendly dropdowns showing both ID and name. This document lists all the API endpoints required to support these lookups.

## Required API Endpoints

### 1. Get All Miqaats
**Endpoint:** `GET /api/miqaats`

**Purpose:** Load all miqaats for the `miqaat_id` filter dropdown.

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Miqaat Name",
      "start_date": "2024-01-01",
      "end_date": "2024-12-31",
      "active_status": true,
      ...
    }
  ]
}
```

**Usage:** Displayed as "ID - Name" in dropdown (e.g., "1 - Miqaat Name")

---

### 2. Get All Events
**Endpoint:** `GET /api/events/all`

**Purpose:** Load all events for the `event_id` filter dropdown (for reporting - returns all events regardless of active status).

**Note:** The existing `/api/events` endpoint only returns events for the active miqaat. Use `/api/events/all` for reporting to get all events.

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Event Name",
      "miqaat_id": 1,
      "date": "2024-01-15",
      "description": "...",
      "miqaat": {
        "id": 1,
        "name": "Miqaat Name",
        ...
      },
      ...
    }
  ]
}
```

**Usage:** Displayed as "ID - Name" in dropdown (e.g., "1 - Event Name")

---

### 3. Get Events by Miqaat ID
**Endpoint:** `GET /api/miqaats/{miqaat_id}/events`

**Purpose:** Load events for a specific miqaat when filtering sharaf definitions by miqaat.

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Event Name",
      "miqaat_id": 1,
      "date": "2024-01-15",
      "description": "...",
      "miqaat": {
        "id": 1,
        "name": "Miqaat Name",
        ...
      },
      ...
    }
  ]
}
```

**Usage:** 
- Fetched when user selects `miqaat_id` and clicks ↻ button
- Used to fetch sharaf definitions for all events in that miqaat
- Displayed as "ID - Name" in dropdown

---

### 4. Get Sharaf Definitions by Event ID
**Endpoint:** `GET /api/events/{event_id}/sharaf-definitions`

**Purpose:** Load sharaf definitions for a specific event when `event_id` is selected in filters.

**Note:** This endpoint requires authentication (user.from.cookie middleware) and filters by user's sharaf type access.

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Sharaf Definition Name",
      "event_id": 1,
      "sharaf_type_id": 1,
      ...
    }
  ]
}
```

**Usage:** 
- Displayed as "ID - Name" in `sharaf_definition_id` dropdown
- Fetched when user clicks fetch button (↻) after selecting `event_id` or `miqaat_id`
- If `event_id` is selected: fetch definitions for that event
- If `miqaat_id` is selected: fetch definitions for all events in that miqaat (may require multiple API calls)

---

### 5. Get Sharaf Positions by Sharaf Definition ID
**Endpoint:** `GET /api/sharaf-definitions/{sharaf_definition_id}/positions`

**Purpose:** Load sharaf positions for a specific sharaf definition when `sharaf_definition_id` is selected.

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Position Name",
      "sharaf_definition_id": 1,
      "order": 1,
      ...
    }
  ]
}
```

**Usage:**
- Displayed as "ID - Name" in `sharaf_position_id` dropdown
- Fetched when user clicks fetch button (↻) after selecting `sharaf_definition_id`
- Only positions for the selected definition are shown

---

### 6. Get Check Definitions by Miqaat ID
**Endpoint:** `GET /api/miqaats/{miqaat_id}/check-definitions`

**Purpose:** Load miqaat check definitions (departments) for a specific miqaat when `miqaat_id` is selected.

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "mcd_id": 1,
      "name": "Check Definition Name",
      "miqaat_id": 1,
      "user_type": "BS",
      "miqaat": {
        "id": 1,
        "name": "Miqaat Name",
        ...
      },
      ...
    }
  ]
}
```

**Usage:**
- Displayed as "ID - Name" in `mcd_id` dropdown
- Fetched when user clicks fetch button (↻) after selecting `miqaat_id`
- Only check definitions for the selected miqaat are shown

---

## Filter Field Mappings

The following filter fields require lookup dropdowns:

| Filter Field | Lookup Type | Context Required | Fetch Trigger | Endpoint |
|-------------|-------------|------------------|---------------|----------|
| `miqaat_id` | Miqaat | No | Loaded on mount | `GET /api/miqaats` |
| `event_id` | Event | No | Loaded on mount | `GET /api/events/all` |
| `sharaf_definition_id` | Sharaf Definition | Yes (event_id or miqaat_id) | User clicks ↻ button | `GET /api/events/{event_id}/sharaf-definitions` |
| `sharaf_position_id` | Sharaf Position | Yes (sharaf_definition_id) | User clicks ↻ button | `GET /api/sharaf-definitions/{id}/positions` |
| `mcd_id` | Check Definition | Yes (miqaat_id) | User clicks ↻ button | `GET /api/miqaats/{miqaat_id}/check-definitions` |
| `currency` | Currency | No | Static list | Static: `["LKR", "USD", "AED", "INR", ...]` |

**Note:** The `wg_id` filter mentioned in the original document refers to wajebaat groups, which are not directly related to events. This may need clarification or a separate lookup endpoint if required.

## Implementation Notes

### Loading Strategy

1. **On Mount (Basic Lookups):**
   - Load all miqaats: `GET /api/miqaats`
   - Load all events: `GET /api/events/all` (use `/all` endpoint for reporting)
   - Currency list is static (no API call needed)

2. **On Demand (Context-Dependent Lookups):**
   - **Sharaf Definitions:** 
     - If `event_id` is selected: `GET /api/events/{event_id}/sharaf-definitions`
     - If `miqaat_id` is selected: First fetch events with `GET /api/miqaats/{miqaat_id}/events`, then fetch definitions for each event
   - **Sharaf Positions:** `GET /api/sharaf-definitions/{sharaf_definition_id}/positions`
   - **Check Definitions:** `GET /api/miqaats/{miqaat_id}/check-definitions`

### User Workflow

1. User selects `event_id` or `miqaat_id` from dropdown
2. User sees ↻ button next to `sharaf_definition_id` field
3. User clicks ↻ to fetch and load sharaf definitions
4. User selects `sharaf_definition_id` from dropdown
5. User sees ↻ button next to `sharaf_position_id` field
6. User clicks ↻ to fetch and load positions for selected definition

### Display Format

All ID-based dropdowns display options in the format:
```
{id} - {name}
```

Example:
- "1 - Ziyafat"
- "2 - Sharaf Definition Name"
- "17 - Position Name"

## Error Handling

All API endpoints should:
- Return `{ success: true, data: [...] }` format on success
- Handle errors gracefully (return empty array if no data)
- Return appropriate error responses:
  - `404` for not found
  - `422` for validation errors
  - `401` for authentication errors (sharaf definitions endpoint)

## Performance Considerations

- Basic lookups (miqaats, events) are loaded once on component mount
- Context-dependent lookups are loaded on-demand to avoid unnecessary API calls
- Results are cached in component state to avoid re-fetching
- Loading states are shown during fetch operations
- For miqaat-based sharaf definitions, consider fetching all events first, then batching definition requests

## Authentication Notes

- **Sharaf Definitions Endpoint** (`/api/events/{event_id}/sharaf-definitions`) requires authentication and filters results based on user's sharaf type access
- All other endpoints are publicly accessible (no authentication required)

## Example API Calls

```javascript
// Load all miqaats
fetch('/api/miqaats')
  .then(res => res.json())
  .then(data => {
    // data.data = array of miqaats
  });

// Load all events (for reporting)
fetch('/api/events/all')
  .then(res => res.json())
  .then(data => {
    // data.data = array of all events
  });

// Load events for a specific miqaat
fetch('/api/miqaats/1/events')
  .then(res => res.json())
  .then(data => {
    // data.data = array of events for miqaat 1
  });

// Load sharaf definitions for an event (requires auth)
fetch('/api/events/1/sharaf-definitions', {
  credentials: 'include' // Include cookies for auth
})
  .then(res => res.json())
  .then(data => {
    // data.data = array of sharaf definitions
  });

// Load positions for a sharaf definition
fetch('/api/sharaf-definitions/1/positions')
  .then(res => res.json())
  .then(data => {
    // data.data = array of positions
  });

// Load check definitions for a miqaat
fetch('/api/miqaats/1/check-definitions')
  .then(res => res.json())
  .then(data => {
    // data.data = array of check definitions
  });
```
