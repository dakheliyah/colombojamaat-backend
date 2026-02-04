# User Roles and Sharaf Access: Backend Changes and Frontend Guide

This document describes the backend changes for **user roles**, **user–sharaf type assignments**, and **sharaf definition visibility**. It is intended for the frontend team to implement session handling, permission-aware UI, and user management in line with the API.

---

## 1. Overview and Goals

- **User roles** are stored in a dedicated table; a user can have **multiple roles** (Admin, Master, Finance, Anjuman, Help Desk, Follow Up).
- Each user can be assigned **multiple sharaf types**. Which sharaf types a user has determines **which sharaf definitions they are allowed to see** for an event.
- The **`users.user_type`** column has been **removed**. Role and sharaf-type data now live in pivot tables and must be read from the User API (or session) and used for UI and permissions.
- **GET /api/events/{eventId}/sharaf-definitions** is **protected**: it requires a valid session (cookie) and returns only sharaf definitions whose `sharaf_type_id` is in the current user’s assigned sharaf types. If the user has no sharaf types assigned, the list is empty.

---

## 2. Schema Summary

### 2.1 New and Changed Tables

| Table | Purpose |
|-------|--------|
| **user_roles** | Lookup table: `id`, `name` (unique), `created_at`, `updated_at`. Holds role names. |
| **user_role** | Pivot: `user_id`, `role_id`. Many-to-many: one user ↔ many roles. |
| **user_sharaf_type** | Pivot: `user_id`, `sharaf_type_id`. Many-to-many: one user ↔ many sharaf types. |
| **users** | **Removed:** `user_type`. No new columns. |

### 2.2 Seed Data for Roles

The following roles exist (seeded in **user_roles**):

- Admin  
- Master  
- Finance  
- Anjuman  
- Help Desk  
- Follow Up  

**Sharaf types** (e.g. Ziyafat, Nikah, Misaq, …) continue to come from **sharaf_types** (see GET /sharaf-types).

### 2.3 Relationships (for API response shape)

- **User**  
  - `roles` → array of `UserRole` (`id`, `name`, …)  
  - `sharaf_types` or `sharafTypes` → array of `SharafType` (`id`, `name`, …)  

- **Sharaf definition**  
  - Belongs to one **sharaf_type** (`sharaf_type_id`).  
  - A user may **see** a sharaf definition only if that definition’s `sharaf_type_id` is in the user’s assigned sharaf types.

---

## 3. Authentication and Session

### 3.1 How the backend identifies the user

- Session is **cookie-based**.  
- Cookie name: **`user`**.  
- Cookie value: the user’s **ITS number** (numeric string).  
- The backend does **not** use Bearer tokens or other headers for this; the `user` cookie is the source of identity on protected routes.

### 3.2 Session check endpoint

- **GET /api/auth/session**  
  - **No** middleware that resolves the full User model.  
  - Returns `{ "its_no": "<value>" }` (200) if the `user` cookie is present and valid (non-empty, numeric).  
  - Returns `{ "error": "unauthorized", "message": "No valid session." }` (401) otherwise.  
  - **Does not** return roles or sharaf types. To get those, the frontend must call a User API (e.g. GET /users/its/{its_no}) with the same ITS number **with credentials** so the cookie is sent.

### 3.3 Where the backend resolves the “current user”

- Only **GET /api/events/{event_id}/sharaf-definitions** uses the “current user” for permission.  
- That route uses middleware **`user.from.cookie`**, which:  
  - Reads the `user` cookie (ITS number).  
  - Looks up **User** by `its_no` and loads **sharafTypes**.  
  - Sets this user as the request user (so `$request->user()` is that User).  
- All other routes **do not** resolve or enforce a “current user” from the cookie. So:  
  - **User list/detail/create/update** do **not** automatically scope to “current user”; they are not protected by this middleware.  
  - **Sharaf definition list** is the only endpoint that both requires a session and filters by the current user’s sharaf types.

---

## 4. API Changes Relevant to the Frontend

### 4.1 GET /api/events/{eventId}/sharaf-definitions

- **Now protected:** The route runs middleware that resolves the user from the `user` cookie.  
- **Behavior:**  
  - If there is **no valid session** (no cookie or cookie not matching a user):  
    - Response: **401**  
    - Body: `{ "success": false, "error": "UNAUTHORIZED", "message": "No valid session." }`  
  - If there **is** a valid session:  
    - Response: **200**  
    - Body: `{ "success": true, "data": [ ... ] }`  
    - **`data`** contains **only** sharaf definitions for that event whose **`sharaf_type_id`** is in the current user’s assigned sharaf types.  
- **Empty list:** If the user has **no** sharaf types assigned, `data` is `[]`.  
- **Query:** `include=positions,payment-definitions` still works; filtering is applied before including relations.  
- **Frontend impact:**  
  - Always send the **`user`** cookie (same-origin or with credentials) when calling this endpoint.  
  - Handle **401**: redirect to login or show “session expired”.  
  - Do **not** assume the list is “all definitions for the event”; it is **filtered by the logged-in user’s sharaf types**.  
  - If the list is empty, either the event has no definitions for the user’s sharaf types, or the user has no sharaf types assigned.

### 4.2 User API: list, show, create, update

- **GET /api/users**  
  - Response includes each user’s **`roles`** and **`sharaf_types`** (or **`sharafTypes`** depending on serialization).  
  - No `user_type` field.  

- **GET /api/users/{id}** and **GET /api/users/its/{its_no}**  
  - Same: **`roles`** and **`sharaf_types`** (or **`sharafTypes`**) are included.  
  - Use these to get the **full current user** (roles + sharaf types) after you have `its_no` from GET /auth/session.  

- **POST /api/users** (create)  
  - **Removed:** `user_type`.  
  - **Added (optional):**  
    - **`role_ids`**: array of integers (IDs from **user_roles**).  
    - **`sharaf_type_ids`**: array of integers (IDs from **sharaf_types**).  
  - If provided, the backend syncs the user’s roles and sharaf types to these IDs.  
  - Example body:  
    `{ "name": "...", "email": "...", "password": "...", "its_no": "12345", "role_ids": [1,2], "sharaf_type_ids": [1,3,5] }`  

- **PUT /api/users/{id}** (update)  
  - **Removed:** `user_type`.  
  - **Added (optional):**  
    - **`role_ids`**: array of integers; replaces the user’s roles.  
    - **`sharaf_type_ids`**: array of integers; replaces the user’s sharaf types.  
  - Sending `role_ids` / `sharaf_type_ids` overwrites the previous set (full sync).  
  - Example: `{ "role_ids": [1], "sharaf_type_ids": [2,4] }`  

- **Validation:**  
  - `role_ids.*` must exist in **user_roles**.  
  - `sharaf_type_ids.*` must exist in **sharaf_types**.  
  - Invalid IDs result in **422** with a validation error.

### 4.3 Other endpoints

- **GET /api/sharaf-types**  
  - Unchanged. Use this to get the list of sharaf types (id + name) for dropdowns when creating/editing users and for display.  

- **GET /api/sharaf-definitions/{id}/positions**, **GET /api/sharaf-definitions/{id}/payment-definitions**, **GET /api/sharaf-definitions/{sd_id}/sharafs**, etc.  
  - **Not** protected by the cookie user. The backend does **not** check here whether the current user is allowed to see that sharaf definition.  
  - **Frontend responsibility:** Only show links/actions to these for sharaf definitions that the user already received in GET /events/{eventId}/sharaf-definitions. That way, the user never gets a “forbidden” definition id from the list in the first place.

---

## 5. How the Backend Enforces Permissions

- **Sharaf definition visibility (list):**  
  - Enforced **only** on **GET /api/events/{eventId}/sharaf-definitions**.  
  - Rule: include a sharaf definition **only if** `sharaf_definition.sharaf_type_id` is in the set of the current user’s assigned sharaf type IDs.  
  - If the user has **no** sharaf types, the list is empty.  
  - **Roles (Admin, Master, etc.) do not** currently change this rule (no “Admin sees all” on the backend).  

- **Other sharaf definition endpoints:**  
  - No backend check that “the current user is allowed to see this definition”.  
  - So the frontend must only expose definition IDs that came from the filtered list.  

- **User management (list/create/update/delete users):**  
  - No backend enforcement of “only admins can create users” or “users can only edit themselves”.  
  - If you need that, it must be enforced in the frontend and/or by future backend middleware/roles.

---

## 6. Limitations

1. **No “list all roles” endpoint**  
   - There is no **GET /api/user-roles**.  
   - To build role dropdowns, the frontend can:  
     - Call **GET /api/users** and derive unique roles from the first user (or any user) that has roles; or  
     - Hardcode the six role names and get IDs from an existing user’s `roles`; or  
     - Rely on the backend to add a **GET /api/user-roles** endpoint later.  

2. **Session only carries ITS number**  
   - GET /auth/session returns only `its_no`.  
   - To get roles and sharaf types for the “current user”, the frontend must call **GET /api/users/its/{its_no}** (with credentials) and use that response for UI and permission logic.  

3. **Roles do not yet change sharaf visibility**  
   - Backend does **not** implement “Admin/Master see all definitions regardless of sharaf type”.  
   - Visibility is **only** by the user’s assigned **sharaf types**.  

4. **Single protected route**  
   - Only **GET /api/events/{eventId}/sharaf-definitions** is protected and filtered by the current user.  
   - All other routes are unchanged with respect to auth and scoping.  

5. **Sharaf definitions with null sharaf_type_id**  
   - If a sharaf definition has `sharaf_type_id = null`, it will **not** be included in the filtered list (because it is not in any user’s sharaf type set).  
   - Assign a sharaf type to definitions if they should be visible.  

6. **Cookie must be sent**  
   - Protected route requires the **cookie** to be sent.  
   - Cross-origin requests must use **credentials: 'include'** (or equivalent) and the backend must allow the origin and credentials (CORS).  

7. **User create/update: full replace for roles and sharaf types**  
   - Sending `role_ids` or `sharaf_type_ids` on update **replaces** the entire set.  
   - To “add one role”, the frontend must send the full new array (e.g. existing IDs + new ID).

---

## 7. Frontend Work Checklist

Use this to align the frontend with the backend behavior and avoid permission gaps.

### 7.1 Session and current user

- [ ] **Send cookie on API requests**  
  - For fetch: `credentials: 'include'` (or equivalent for your HTTP client).  
  - Ensure the `user` cookie is set after login and sent to the API base URL.  

- [ ] **Resolve “current user” for permission and UI**  
  - After login (or on app load if already logged in):  
    1. Call **GET /api/auth/session**.  
    2. If 401, treat as not logged in (redirect to login, clear local state).  
    3. If 200, use `its_no` to call **GET /api/users/its/{its_no}** (with credentials).  
    4. Store in app state: `currentUser` with `id`, `name`, `email`, `its_no`, `roles`, `sharaf_types` (or `sharafTypes`).  
  - Use this object for:  
    - Showing/hiding features by role (e.g. “Admin only” sections).  
    - Knowing which sharaf types the user can see (for labels/filters if needed).  

- [ ] **Handle 401 on GET /events/{eventId}/sharaf-definitions**  
  - If the response is 401, do not render the sharaf definitions list; show “Session expired” or redirect to login and clear session.  

### 7.2 Sharaf definitions list and navigation

- [ ] **Use only the filtered list for navigation**  
  - Call **GET /api/events/{eventId}/sharaf-definitions** with credentials.  
  - Use the returned list as the **only** source of sharaf definitions for that event for this user.  
  - Do **not** fetch “all” definitions from another endpoint and show them.  

- [ ] **Empty list**  
  - If `data` is `[]`, show a clear message (e.g. “No sharaf definitions available for your access” or “No sharaf types assigned to your account”) and do not show links to definitions.  

- [ ] **Links to positions, payment-definitions, sharafs**  
  - Only link to **/sharaf-definitions/{id}/positions**, **/sharaf-definitions/{id}/payment-definitions**, **/sharaf-definitions/{sd_id}/sharafs** for definition IDs that appear in the filtered list.  
  - Do not let the user navigate to a definition ID they never received in the list (to avoid relying on endpoints that don’t enforce visibility).  

### 7.3 User management UI (admin / user CRUD)

- [ ] **Remove `user_type`**  
  - Remove any dropdown or field that sets “user type” (BS, Admin, Help Desk, etc.) on create/update.  
  - Replace with:  
    - **Roles:** multi-select or checkboxes for roles, sending **`role_ids`** (array of **user_roles.id**).  
    - **Sharaf types:** multi-select or checkboxes for sharaf types, sending **`sharaf_type_ids`** (array of **sharaf_types.id**).  

- [ ] **Load options for roles**  
  - Use one of: GET /users (and derive roles from response), hardcoded role names + IDs from a user, or a future GET /user-roles.  
  - Ensure IDs sent in `role_ids` match **user_roles.id**.  

- [ ] **Load options for sharaf types**  
  - Use **GET /api/sharaf-types** and map `id` and `name` to the multi-select for **`sharaf_type_ids`**.  

- [ ] **Create user**  
  - Send **role_ids** and **sharaf_type_ids** in the POST body when the admin assigns roles and sharaf types.  

- [ ] **Update user**  
  - Send **role_ids** and **sharaf_type_ids** when the admin changes roles or sharaf types; this **replaces** the previous sets.  
  - When opening the edit form, prefill from the user’s `roles` and `sharaf_types` (array of objects with `id`).  

- [ ] **Display**  
  - In user list/detail, show role names (from `roles[].name`) and sharaf type names (from `sharaf_types[].name` or `sharafTypes[].name`) instead of a single “user type”.  

### 7.4 Role-based UI (optional but recommended)

- [ ] **Use `currentUser.roles` for UI**  
  - Backend does not enforce “only Admin can do X” on most routes.  
  - Frontend should:  
    - Hide “User management” or “Create user” from users who don’t have an “Admin” (or appropriate) role.  
    - Hide or show menu items based on role names.  
  - Prefer using role **names** (e.g. `roles.some(r => r.name === 'Admin')`) so the app works even if backend role IDs change.  

- [ ] **Assign sharaf types for new users**  
  - If a new user has no **sharaf_type_ids**, they will see **no** sharaf definitions on GET /events/…/sharaf-definitions.  
  - Encourage admins to assign at least one sharaf type when creating users who need to see sharaf definitions.  

---

## 8. Example API Response Shapes

### 8.1 GET /api/auth/session (200)

```json
{
  "its_no": "12345"
}
```

### 8.2 GET /api/users/its/12345 (200) – current user with roles and sharaf types

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "its_no": "12345",
    "email_verified_at": null,
    "created_at": "...",
    "updated_at": "...",
    "roles": [
      { "id": 1, "name": "Admin", "created_at": "...", "updated_at": "..." }
    ],
    "sharaf_types": [
      { "id": 1, "name": "Ziyafat", "created_at": "...", "updated_at": "..." },
      { "id": 2, "name": "Nikah", "created_at": "...", "updated_at": "..." }
    ]
  }
}
```

(Laravel may serialize the relation as `sharaf_types` or `sharafTypes` depending on config; normalize in the frontend if needed.)

### 8.3 GET /api/events/1/saraf-definitions (401 – no/invalid session)

```json
{
  "success": false,
  "error": "UNAUTHORIZED",
  "message": "No valid session."
}
```

### 8.4 GET /api/events/1/sharaf-definitions (200 – filtered)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "event_id": 1,
      "sharaf_type_id": 1,
      "name": "Main Ziyafat",
      "key": null,
      "description": null,
      "created_at": "...",
      "updated_at": "...",
      "sharaf_type": { "id": 1, "name": "Ziyafat", "created_at": "...", "updated_at": "..." }
    }
  ]
}
```

Only definitions whose `sharaf_type_id` is in the current user’s assigned sharaf types appear here.

### 8.5 POST /api/users (201 – create with roles and sharaf types)

**Request body:**

```json
{
  "name": "New User",
  "email": "new@example.com",
  "password": "securepassword",
  "its_no": "67890",
  "role_ids": [2, 3],
  "sharaf_type_ids": [1, 2, 5]
}
```

**Response:** `data` will include the new user with `roles` and `sharaf_types` (or `sharafTypes`) loaded.

### 8.6 PUT /api/users/2 (update roles and sharaf types)

**Request body (partial):**

```json
{
  "role_ids": [1],
  "sharaf_type_ids": [1, 2, 3, 4]
}
```

This **replaces** the user’s roles and sharaf types with these IDs.

---

## 9. Quick Reference

| Item | Detail |
|------|--------|
| Session | Cookie name: **`user`**, value: ITS number (numeric string). |
| Current user (full) | GET /auth/session → `its_no`; then GET /users/its/{its_no} with credentials. |
| Protected route | GET /api/events/{event_id}/sharaf-definitions (requires cookie; returns 401 if no valid user). |
| Visibility rule | User sees only sharaf definitions whose `sharaf_type_id` is in their assigned sharaf types. |
| User create/update | Use **role_ids** (user_roles.id) and **sharaf_type_ids** (sharaf_types.id); no **user_type**. |
| Role list for UI | No GET /user-roles; derive from GET /users or hardcode names and get IDs from a user. |
| Sharaf type list | GET /api/sharaf-types. |
| Empty sharaf types | User sees no sharaf definitions (empty array from API). |

---

*Document version: 1.0. Reflects backend state after implementation of user_roles, user_role, user_sharaf_type, removal of users.user_type, and sharaf definition filtering by user’s sharaf types.*
