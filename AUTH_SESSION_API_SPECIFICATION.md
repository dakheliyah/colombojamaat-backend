# Auth Session API — Backend Implementation Specification

This document is a **standalone specification** for implementing the **Auth Session** API in the backend. The backend is a separate project with no visibility into the frontend codebase. Everything needed to implement this API is described below.

---

## 1. Purpose and context

### 1.1 Why this API exists

Users authenticate via **OneLogin** (or a similar flow) on another domain (e.g. `colombojamaat.org`). After login, that system sets a cookie named **`its_no`** containing the user’s ITS number (encrypted). The frontend application (e.g. `bethak.colombojamaat.org`) needs this ITS number to log the user in.

The **`its_no`** cookie is set with the **HttpOnly** flag. Browsers do **not** expose HttpOnly cookies to JavaScript. So the frontend cannot read `its_no` via `document.cookie` or any other client-side API. The cookie is, however, sent automatically by the browser with every request to the same site (or configured domains) when the request is made with **credentials** (e.g. `credentials: 'include'` in `fetch`).

Therefore, the frontend calls a **backend** endpoint with credentials. The backend reads the **`its_no`** cookie from the incoming HTTP request, decrypts it (if needed), and returns the plain ITS number in the response body. The frontend then uses that ITS number to complete login. No frontend code can read the HttpOnly cookie; only the server can.

### 1.2 What the backend must do

- Expose **one** endpoint: **`GET /api/auth/session`** (or equivalent under your API prefix).
- For each request:
  - Read the **`its_no`** cookie from the request (Cookie header).
  - If the cookie is missing, expired, or invalid → return **401** (or **404**).
  - If the cookie is present and valid → decrypt it using the **same method and key** as the system that sets the cookie, then return the decrypted ITS number in the response body.
- Ensure **CORS** is configured so that the frontend origin can call this endpoint **with credentials** (cookies sent). This requires supporting `credentials` and returning the correct `Access-Control-*` headers.

---

## 2. API contract

### 2.1 Endpoint

| Method | Path (example)        | Description                                |
|--------|------------------------|--------------------------------------------|
| GET    | `/api/auth/session`   | Return the current user’s ITS number from the `its_no` cookie, or an error. |

The path may be under a prefix (e.g. `/api/v1/auth/session`) as long as the frontend is configured to call that full URL. This spec uses **`/api/auth/session`** as the canonical path.

### 2.2 Request

- **Method:** `GET`
- **Body:** None
- **Headers:** The client will send the request with **credentials** (cookies). The browser will include the **Cookie** header automatically for the relevant domain (e.g. `.colombojamaat.org`).
- **Query parameters:** None required

The backend must be able to read the **`its_no`** cookie from the request. Cookie name is exactly: **`its_no`**.

### 2.3 Response — success

When the **`its_no`** cookie is present and valid (decryption succeeds and yields a non-empty ITS number):

- **Status:** `200 OK`
- **Content-Type:** `application/json`
- **Body:** A JSON object with a single key **`its_no`** whose value is the **plain ITS number string** (no encryption, no extra whitespace). The frontend will trim the value; trimming on the backend is still recommended.

**Example:**

```json
{
  "its_no": "30361286"
}
```

- The value must be a **string**. Typically an 8-digit ITS number, but the backend should not change the value; return whatever the decrypted cookie contains (after trimming).
- Do **not** return additional fields unless the frontend is updated to use them. The minimum contract is **`{ "its_no": "<string>" }`**.

### 2.4 Response — no or invalid session

When the **`its_no`** cookie is missing, expired, malformed, or decryption fails:

- **Status:** `401 Unauthorized` (or `404 Not Found`; the frontend treats both as “no session”).
- **Content-Type:** `application/json`
- **Body (example for 401):**

```json
{
  "error": "unauthorized",
  "message": "No valid session."
}
```

You can use a different structure (e.g. `success: false`, `message` only); the frontend only checks **HTTP status** and, for 2xx, the presence of **`its_no`** in the JSON body. So any non-2xx status is treated as “no session.”

### 2.5 Other status codes

- **500 Internal Server Error** — Use for unexpected server errors (e.g. decryption library failure). The frontend will treat this as “no session” and may redirect the user to login again.

---

## 3. Cookie details

### 3.1 Cookie name and source

- **Name:** `its_no`
- **Set by:** The system that performs OneLogin (or equivalent) authentication — possibly the same backend, possibly another service (e.g. colombojamaat.org). The cookie is usually set for a parent domain (e.g. `.colombojamaat.org`) so that subdomains (e.g. `bethak.colombojamaat.org` and the API host) receive it.
- **Attributes (typical):** HttpOnly, Secure, SameSite=Lax or similar, Path=/

The backend does **not** set this cookie in this spec; it only **reads** it from the incoming request.

### 3.2 Cookie value format (when encrypted)

If the cookie value is **encrypted** (common), the backend must decrypt it using the **same algorithm and key** as the system that sets the cookie. The following describes a typical format used in the same ecosystem; if your cookie is set by another system, you must use **that** system’s decryption logic and key.

- **Encoding:** The value in the `Cookie` header may be **URL-encoded** (e.g. `%2B` for `+`). Decode the cookie value (e.g. `urldecode`) before decryption.
- **Cipher:** AES-256-CBC.
- **Payload:** After URL decoding, the value is **Base64**. After Base64 decoding, the binary layout is:
  - **First 16 bytes:** IV (initialization vector).
  - **Remaining bytes:** Ciphertext.
- **Key:** 32-byte (256-bit) key, usually provided as a 64-character hex string in configuration. The key must match the one used when the cookie was set (same as in the system that performs OneLogin / sets `its_no`).

**Steps (pseudocode):**

1. Read cookie `its_no` from the request.
2. If missing or empty → return 401 (or 404).
3. URL-decode the value.
4. Base64-decode the result.
5. If length ≤ 16 → invalid, return 401.
6. First 16 bytes = IV; remainder = ciphertext.
7. Decrypt with AES-256-CBC using the configured key and this IV.
8. Decode decrypted bytes to UTF-8 string; trim.
9. If empty or invalid → return 401; otherwise return 200 with `{ "its_no": "<decrypted>" }`.

If the cookie is set by **another** service (e.g. colombojamaat.org), obtain the decryption method and key from that team and implement accordingly. The critical point is: **the backend must return the same plain ITS number that was originally encrypted into the cookie.**

### 3.3 Plain cookie value

If in your environment the **`its_no`** cookie is stored **in plain text** (no encryption), then skip decryption and return the trimmed value in `{ "its_no": "..." }`. Still return 401 when the cookie is missing or empty.

---

## 4. CORS and credentials

The client calls this API with **credentials** (cookies). For the browser to send the **`its_no`** cookie and allow the frontend to read the response, the backend must support **cross-origin requests with credentials**.

### 4.1 Required behavior

- **Allow credentials:** Responses must include  
  `Access-Control-Allow-Credentials: true`
- **Allow specific origin:** Responses must include  
  `Access-Control-Allow-Origin: <frontend-origin>`  
  where `<frontend-origin>` is the exact origin of the frontend (e.g. `https://bethak.colombojamaat.org`).  
  **Do not** use `*` for `Access-Control-Allow-Origin` when using credentials; the browser will reject it.
- **Allow the method:**  
  `Access-Control-Allow-Methods` must include `GET` (for the route that serves `/api/auth/session`).
- **Allow relevant headers:** If the client sends custom headers, list them in `Access-Control-Allow-Headers`, or use a minimal set that includes what you need. For a simple `GET` with cookies, often no custom headers are required.

### 4.2 Preflight (OPTIONS)

If the frontend or browser sends an **OPTIONS** preflight request for this endpoint, respond with **204 No Content** (or 200) and the same CORS headers above (including `Access-Control-Allow-Credentials: true` and the specific `Access-Control-Allow-Origin`).

### 4.3 Allowed origins

Configure the list of allowed frontend origins (e.g. production, staging) in environment or config, and set `Access-Control-Allow-Origin` to the **request’s Origin** only when it is in that list. Do not reflect arbitrary origins in production.

**Example allowed origins (configurable):**

- `https://bethak.colombojamaat.org`
- `https://staging.bethak.colombojamaat.org` (if used)
- `http://localhost:5173` (if the frontend runs there during development)

---

## 5. Security considerations

- **Do not log** the decrypted ITS number or the raw cookie value in application or access logs. Log only presence/absence or “session valid/invalid” if needed.
- **Validate** the decrypted value (e.g. non-empty, expected format such as digits only) before returning it. If invalid, return 401.
- **Use HTTPS** in production so the `its_no` cookie (Secure) is sent only over TLS.
- **Keep the decryption key** in configuration or secrets (environment variables, secret manager). Do not hardcode it in source.
- **Rate limiting:** Consider applying rate limiting to `GET /api/auth/session` to reduce abuse (e.g. brute force or enumeration). The frontend may call this on initial load and when checking auth state.

---

## 6. Summary checklist for implementers

- [ ] Implement **GET /api/auth/session** (or your API-prefixed path).
- [ ] Read the **`its_no`** cookie from the request (Cookie header).
- [ ] If cookie missing/empty or decryption fails or result invalid → return **401** (or 404) with JSON body.
- [ ] If valid → decrypt (if applicable) and return **200** with JSON **`{ "its_no": "<plain ITS number>" }`**.
- [ ] Use the **same decryption algorithm and key** as the system that sets the `its_no` cookie (or plain value if not encrypted).
- [ ] Set CORS: **Access-Control-Allow-Credentials: true** and **Access-Control-Allow-Origin: <frontend-origin>** (no `*` with credentials).
- [ ] Support **OPTIONS** for the route if the client sends preflight.
- [ ] Do not log ITS number or raw cookie value; use HTTPS; keep key in config/secrets.

---

## 7. Example flow (client perspective)

The following is for illustration only; the backend does not need to implement the client.

1. User has already logged in via OneLogin; the **`its_no`** cookie (HttpOnly) is set for the domain.
2. The frontend loads and calls:  
   `GET https://<api-host>/api/auth/session`  
   with **credentials: 'include'** (or equivalent), so the browser sends the **`its_no`** cookie.
3. Backend receives the request, reads **`its_no`**, decrypts (if needed), and returns either:
   - **200** and **`{ "its_no": "30361286" }`** → frontend uses the value to complete login, or
   - **401** (or 404) → frontend treats as “no session” and may redirect to OneLogin after a delay.

No frontend code ever reads the cookie; the backend is the only place that can read the HttpOnly **`its_no`** and expose it safely via this API.
