# Backend: Logout API (clear session cookie)

The frontend calls a logout endpoint when the user clicks Logout so the backend can invalidate the session and clear the session cookie. This document describes the API the backend must implement.

## Frontend behavior

On logout the frontend:

1. Calls **POST /api/auth/logout** with `credentials: 'include'` (so the session cookie is sent).
2. Then clears local auth state (localStorage and React state) and the user is redirected to the login page via existing routing (`ProtectedRoute` / `RootRedirect`).

If the backend endpoint is missing (404) or method not allowed (405), the frontend still completes logout locally and redirects to `/login`; it does not block or show an error. Any other non-OK response is logged to the console but does not block logout.

## API the backend must implement

### POST /api/auth/logout

- **Method:** `POST`
- **URL:** Same base as other auth routes, e.g. `/api/auth/logout` (no trailing slash required).
- **Request:**
  - **Headers:** `Content-Type: application/json` (body may be empty).
  - **Body:** Optional; frontend sends an empty body. Backend may ignore body.
  - **Credentials:** The request is sent with `credentials: 'include'`, so the browser will send the session cookie (e.g. `user` or whatever cookie the backend sets at login). The backend must receive this cookie to know which session to invalidate.

- **Expected behavior:**
  - Invalidate the current session (e.g. remove from server-side store or mark as expired).
  - Clear the session cookie in the response by sending a `Set-Cookie` header that expires or clears the cookie, e.g.:
    - `Set-Cookie: user=; Path=/; Max-Age=0` (or `Expires` in the past), or
    - whatever cookie name and path the backend uses for the session.

- **Response:**
  - **Status:** `200 OK` (or `204 No Content`) on success.
  - **Body:** Optional. Frontend does not rely on the response body; a simple `{ "success": true }` or empty body is fine.

- **Errors:**
  - If the request has no valid session cookie, the backend may still respond with `200` (idempotent “already logged out”) or `401`. The frontend will have already cleared local state, so either is acceptable.

## Summary

| Item        | Value |
|------------|--------|
| Method     | `POST` |
| Path       | `/api/auth/logout` |
| Credentials| Yes (cookie sent) |
| Request body | Empty or `{}` |
| Success    | 200 (or 204); clear session cookie via `Set-Cookie` |
| Frontend   | Then clears localStorage and redirects to `/login` |
