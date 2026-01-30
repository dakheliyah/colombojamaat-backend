# Mumin Dashboard Profile API — Backend Implementation (Completed)

This document describes the backend API implemented for the Mumin Dashboard expansion. The implementation is complete.

## Implemented Endpoints

### 1. `GET /api/miqaats/{miqaat_id}/mumin-profile/{its_id}`

**Purpose:** Resolve and return the full profile for the Mumin Dashboard in one call.

**Logic:**
1. Check `wajebaat_groups` WHERE `miqaat_id` = X AND `its_id` = Y. If found:
   - Get `wg_id`, `master_its`, `group_name`
   - Get all members: SELECT its_id FROM wajebaat_groups WHERE miqaat_id = X AND wg_id = (resolved wg_id)
   - Set `profile_type: "group"`, `master_its`, `wg_id`, `group_name`
2. Else (not in group):
   - Get census for its_id → `hof_id`
   - Get family: HOF + census where hof_id = hof_id
   - Set `profile_type: "family"`, `hof_its: hof_id`
3. For the resolved list of ITSs (from group or family):
   - Fetch census record for each
   - Fetch wajebaat for each WHERE miqaat_id = X AND its_id IN (...)
   - Include `is_isolated` and `category` (join waj_categories on wc_id) in each wajebaat

**Response format:** See plan file `mumin_dashboard_expansion_4b42c77e.plan.md`.

---

### 2. `GET /api/miqaats/{miqaat_id}/wajebaat/{its_id}` (Updated)

**Change:** Now returns `is_isolated` and `category` (wc_id, name, hex_color) in the wajebaat object.

---

### 3. `GET /api/miqaats/{miqaat_id}/wajebaat/by-its-list` (Optional)

**Purpose:** Fetch wajebaat records for multiple ITSs in one call.

**Query params:** `its_ids=123,456,789` (comma-separated)

**Response:** Array of wajebaat objects with `is_isolated` and `category` populated.
