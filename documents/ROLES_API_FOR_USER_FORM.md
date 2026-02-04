# GET /roles – All assignable roles for user create/edit

The Create User and Edit User forms need to show **all** assignable roles (Admin, Master, Finance, Anjuman, Help Desk, Follow Up). Previously the frontend derived roles only from the users list, so roles that no user had (e.g. Admin, Follow Up) never appeared.

## Backend contract

- **Endpoint:** `GET /api/roles` (or whatever your API base path is; the frontend uses `API_BASE_URL + '/roles'`).
- **Response:** JSON array of role objects, or `{ success: true, data: [...] }`.

Each role object must include at least:

- `id` (number) – used when creating/updating users (`role_ids`).
- `name` (string) – e.g. `"Admin"`, `"Master"`, `"Finance"`, `"Anjuman"`, `"Help Desk"`, `"Follow Up"`.

Example:

```json
[
  { "id": 1, "name": "Admin" },
  { "id": 2, "name": "Master" },
  { "id": 3, "name": "Finance" },
  { "id": 4, "name": "Anjuman" },
  { "id": 5, "name": "Help Desk" },
  { "id": 6, "name": "Follow Up" }
]
```

Or wrapped:

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Admin" },
    ...
  ]
}
```

## Frontend behavior

- The frontend calls `GET /roles` first to load role options.
- If the endpoint returns **404** (or any error), it **falls back** to the previous behavior: it loads roles from the users list (only roles that appear on at least one user). So existing backends without `GET /roles` keep working.
- Once you add `GET /roles` and return all assignable roles, Admin and Follow Up (and any new roles) will appear in the Roles (module access) list.

## Summary

- **Add:** `GET /api/roles` returning all assignable roles with `id` and `name`.
- **Result:** Create/Edit User forms show Admin, Follow Up, and any other roles you define, even if no user has them yet.
