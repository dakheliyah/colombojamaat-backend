# Sila Fitra – API and schema specification

**Audience:** Backend team (miqaat backend repo)

---

## 1. Schema changes

All new; no modifications to existing tables.

### 1.1 Table: `sila_fitra_config`

Stores per-miqaat rates used for the calculator (admin-configured).

| Column                     | Type            | Nullable | Default | Description                        |
| -------------------------- | --------------- | -------- | ------- | ---------------------------------- |
| id                         | bigint UNSIGNED | NO       | -       | Primary key, auto_increment        |
| miqaat_id                  | bigint UNSIGNED | NO       | -       | FK → miqaats.id                    |
| misaqwala_rate             | decimal(10,2)   | NO       | -       | Rate per Misaqwala                 |
| non_misaq_hamal_mayat_rate | decimal(10,2)   | NO       | -       | Rate per Non-Misaq / Hamal / Mayat |
| currency                   | varchar(3)      | NO       | 'LKR'   | e.g. LKR, INR                      |
| created_at                 | timestamp       | YES      | NULL    |                                    |
| updated_at                 | timestamp       | YES      | NULL    |                                    |

- **Unique:** `miqaat_id` (one config per miqaat).
- **Index:** `miqaat_id`.
- **Foreign key:** `miqaat_id` → `miqaats.id` ON DELETE CASCADE.

### 1.2 Table: `sila_fitra_calculations`

One row per household (miqaat + hof_its): counts, calculated amount, optional receipt, verification.

| Column            | Type            | Nullable | Default | Description                          |
| ----------------- | --------------- | -------- | ------- | ------------------------------------ |
| id                | bigint UNSIGNED | NO       | -       | Primary key, auto_increment          |
| miqaat_id         | bigint UNSIGNED | NO       | -       | FK → miqaats.id                      |
| hof_its           | varchar(255)    | NO       | -       | Head of Family ITS                   |
| misaqwala_count   | int UNSIGNED    | NO       | 0       |                                      |
| non_misaq_count   | int UNSIGNED    | NO       | 0       |                                      |
| hamal_count       | int UNSIGNED    | NO       | 0       |                                      |
| mayat_count       | int UNSIGNED    | NO       | 0       |                                      |
| haj_e_badal       | int UNSIGNED    | YES      | NULL    | Optional                              |
| calculated_amount | decimal(10,2)   | NO       | -       | Total amount                         |
| currency          | varchar(3)      | NO       | 'LKR'   |                                      |
| receipt_path      | varchar(500)    | YES      | NULL    | Stored file path or URL after upload |
| payment_verified  | tinyint(1)      | NO       | 0       | 0 = pending, 1 = verified            |
| verified_by_its   | varchar(255)    | YES      | NULL    | ITS of finance user who verified     |
| verified_at       | timestamp       | YES      | NULL    | When verified                         |
| created_at        | timestamp       | YES      | NULL    |                                      |
| updated_at        | timestamp       | YES      | NULL    |                                      |

- **Unique:** `(miqaat_id, hof_its)` so one calculation per household per miqaat (upsert on save).
- **Indexes:** `miqaat_id`, `hof_its`, `payment_verified` (for Finance filters if needed).
- **Foreign key:** `miqaat_id` → `miqaats.id` ON DELETE CASCADE.

---

## 2. APIs

Base path assumed: `/api` (or your existing API prefix). Auth: same as rest of app (session/cookie or Bearer).

### 2.1 Get Sila Fitra config (Admin & Mumin)

- **GET** `/api/miqaats/:miqaatId/sila-fitra-config`
- **Response (200):**

```json
{
  "id": 1,
  "miqaat_id": 1,
  "misaqwala_rate": "150.00",
  "non_misaq_hamal_mayat_rate": "75.00",
  "currency": "LKR",
  "created_at": "...",
  "updated_at": "..."
}
```

- **404** if no config for that miqaat.

---

### 2.2 Create or update Sila Fitra config (Admin)

- **PUT** `/api/miqaats/:miqaatId/sila-fitra-config`
- **Body (JSON):**

```json
{
  "misaqwala_rate": 150,
  "non_misaq_hamal_mayat_rate": 75,
  "currency": "LKR"
}
```

- **Response (200 or 201):** Same shape as 2.1.
- **Validation:** Both rates required, non-negative; currency optional (default LKR).

---

### 2.3 Get current user's Sila Fitra calculation (Mumin)

- **GET** `/api/miqaats/:miqaatId/sila-fitra/me`

**Per-HOF behaviour:** Sila Fitra is stored per HOF. The backend must resolve the logged-in user's **household** (e.g. from session ITS + census/family data) and return the calculation for that household's **hof_its**. So both the HOF and any family member get the same record—everyone in the family sees the same Sila Fitra amount and status.

- **Response (200):** Single calculation object, or empty/null if none.

```json
{
  "id": 1,
  "miqaat_id": 1,
  "hof_its": "ITS001",
  "misaqwala_count": 2,
  "non_misaq_count": 1,
  "hamal_count": 0,
  "mayat_count": 0,
  "haj_e_badal": null,
  "calculated_amount": "375.00",
  "currency": "LKR",
  "receipt_path": "sila-fitra/1/ITS001/receipt.jpg",
  "payment_verified": 0,
  "verified_by_its": null,
  "verified_at": null,
  "created_at": "...",
  "updated_at": "..."
}
```

- **200** with `null` or `{}` if no record yet.

---

### 2.4 Save Sila Fitra calculation (Mumin)

- **POST** `/api/miqaats/:miqaatId/sila-fitra/save`
- **Body (JSON):**

```json
{
  "hof_its": "ITS001",
  "misaqwala_count": 2,
  "non_misaq_count": 1,
  "hamal_count": 0,
  "mayat_count": 0,
  "haj_e_badal": null,
  "calculated_amount": 375.00,
  "currency": "LKR"
}
```

- **Response (200 or 201):** Full calculation object (same shape as 2.3). Optional field `haj_e_badal` (integer, nullable) is accepted on save. If a row for (miqaat_id, hof_its) exists, update it; otherwise insert.
- **Authorization:** The authenticated user must be allowed to act for this household: either they are the HOF (session ITS = hof_its) or they are a family member (session ITS belongs to the same family as hof_its, per census/family resolution). So any family member can save on behalf of the HOF.

---

### 2.5 Upload Sila Fitra receipt (Mumin)

- **POST** `/api/miqaats/:miqaatId/sila-fitra/receipt`
- **Content-Type:** `multipart/form-data`
- **Field name:** `receipt` (or `receipt_image`) – single image file (e.g. image/jpeg, image/png). Max size recommend e.g. 5MB.
- **Response (200):**

```json
{
  "receipt_path": "sila-fitra/1/ITS001/abc123.jpg",
  "calculation_id": 1
}
```

- Backend should associate the file with the current user's **household** calculation (same resolution as 2.3: resolve hof_its from session so both HOF and any family member upload to the same record). If no calculation exists, return **400** with a message to save calculation first.
- **400** for invalid/missing file or no calculation.

---

### 2.6 List Sila Fitra submissions (Finance)

- **GET** `/api/miqaats/:miqaatId/sila-fitra/submissions`
- **Query (optional):** `verified=0|1` to filter by `payment_verified`.
- **Response (200):**

```json
{
  "data": [
    {
      "id": 1,
      "miqaat_id": 1,
      "hof_its": "ITS001",
      "misaqwala_count": 2,
      "non_misaq_count": 1,
      "hamal_count": 0,
      "mayat_count": 0,
      "haj_e_badal": null,
      "calculated_amount": "375.00",
      "currency": "LKR",
      "receipt_path": "sila-fitra/1/ITS001/receipt.jpg",
      "payment_verified": 0,
      "verified_by_its": null,
      "verified_at": null,
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

- **Authorization:** Restrict to Finance (or appropriate role).

---

### 2.7 Verify Sila Fitra payment (Finance)

- **PATCH** `/api/miqaats/:miqaatId/sila-fitra/:calculationId/verify`
- **Body (JSON):**

```json
{
  "verified": true
}
```

- **Response (200):** Updated calculation object (same shape as 2.3), with `payment_verified`, `verified_by_its`, `verified_at` set.
- **Authorization:** Finance only. Set `verified_by_its` from current user's ITS and `verified_at` to current timestamp.

---

### 2.8 Receipt image URL (serving files)

- Finance (and optionally Mumin) need a URL to display the receipt image. Either:
  - **Option A:** Backend serves files at a route, e.g. **GET** `/api/miqaats/:miqaatId/sila-fitra/receipt/:calculationId` returning the image (with auth), or
  - **Option B:** Store a public or signed URL in `receipt_path` (e.g. S3/CDN) and frontend uses that.

Document the chosen option and the exact URL shape so the frontend can show the image in FinanceView and (if needed) in MuminView.

**Backend implementation (Option A):** Receipt images are served by the backend. Use **GET** `/api/miqaats/{miqaatId}/sila-fitra/receipt/{calculationId}` with the same auth (cookie/session) as the rest of the API. Authorization: the current user must be Finance or a member of the household (same HOF as the calculation). Response is the image file (Content-Type: image/jpeg, image/png, etc.; Content-Disposition: inline). The `receipt_path` stored in the database is a relative path under private storage (e.g. `sila-fitra/1/ITS001/uuid.jpg`); the frontend should use the URL above to display the image, not `receipt_path` directly.

---

## 3. Calculation formula

- **Base = (misaqwala_count × misaqwala_rate) + (non_misaq_count + hamal_count + mayat_count) × non_misaq_hamal_mayat_rate**
- **Total = Base + (haj_e_badal ?? 0)** — when `haj_e_badal` is present it is treated as an additive amount in the same currency and added to the base for validation.
- Frontend will compute this in the modal using config; backend validates on save that `calculated_amount` matches this formula for consistency.

---

## 4. Summary checklist for backend

- Migration: create `sila_fitra_config` and `sila_fitra_calculations` with indexes and FKs.
- Implement endpoints 2.1–2.7 (and 2.8 if serving files yourself).
- Auth: Admin for config; Mumin for me/save/receipt; Finance for submissions and verify.
- File storage for receipt (path or URL) and, if applicable, a route to serve the image securely.
