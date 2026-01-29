# Wajebaat API Specification

**Version:** 1.0.0  
**Base URL:** `/api`  
**Last Updated:** January 28, 2026

This document provides a complete specification for the Wajebaat (Takhmeen / Finance Ada) module API endpoints. Use this specification to implement frontend integration.

---

## Table of Contents

1. [Overview](#overview)
2. [Data Models](#data-models)
3. [Endpoints](#endpoints)
   - [Takhmeen Store](#1-takhmeen-store)
   - [Finance Ada Update](#2-finance-ada-update)
4. [Business Logic Rules](#business-logic-rules)
5. [Error Handling](#error-handling)
6. [Examples](#examples)

---

## Overview

The Wajebaat module handles:
- **Takhmeen**: Bulk assignment of wajebaat amounts to members
- **Finance Ada**: Payment status tracking with department clearance requirements
- **Group Management**: Automatic group membership detection and member data retrieval
- **Categorization**: Automatic category assignment based on amount slabs
- **Currency Support**: Multi-currency support with conversion rates for reporting

### Key Features

- ✅ Bulk operations for efficient data entry
- ✅ Automatic group membership detection
- ✅ Department clearance guard (blocks payment if checks pending)
- ✅ Multi-currency support (currency stored, conversion_rate for reporting only)
- ✅ Automatic categorization based on amount ranges

---

## Data Models

### Wajebaat Record

```typescript
interface Wajebaat {
  id: number;
  miqaat_id: number;
  its_id: string;
  wg_id: number | null;              // Group ID if member belongs to a group
  amount: number;                     // Decimal, stored in original currency
  currency: string;                    // 3-character currency code (e.g., "LKR", "USD")
  conversion_rate: number;            // For reporting only, does not affect stored amount
  status: boolean;                    // Payment status: false = unpaid, true = paid
  wc_id: number | null;               // Category ID (auto-assigned based on amount)
  created_at: string;                  // ISO 8601 datetime
  updated_at: string;                 // ISO 8601 datetime
}
```

### Group Member Data

```typescript
interface GroupMember {
  its_id: string;
  person: Census | null;               // Census record for the member
  wajebaat: Wajebaat | null;          // Wajebaat record for the member
}

interface GroupData {
  wg_id: number;
  master_its: string;                  // ITS ID of the group master
  members: GroupMember[];             // All members in the group
}
```

### Pending Department

```typescript
interface PendingDepartment {
  mcd_id: number;                      // Department check ID
  name: string;                        // Department name (e.g., "Finance", "Clearance")
}
```

### Census Record (Reference)

```typescript
interface Census {
  its_id: string;
  hof_id: string;
  name: string | null;
  arabic_name: string | null;
  age: number | null;
  gender: string | null;
  mobile: string | null;
  email: string | null;
  // ... other census fields
}
```

---

## Endpoints

### 1. Takhmeen Store

**Endpoint:** `POST /api/wajebaat/takhmeen`

**Description:**  
Bulk-save Takhmeen amounts for multiple members. If an `its_id` is passed (as a separate field), the API checks if they belong to a `wajebaat_groups` and returns all associated members' data.

**Request Headers:**
```
Content-Type: application/json
```

**Request Body:**
```typescript
interface TakhmeenStoreRequest {
  miqaat_id: number;                   // Required: ID of the miqaat
  entries: TakhmeenEntry[];           // Required: Array of 1-1000 entries
  its_id?: string;                     // Optional: If provided, returns group data for this ITS
}

interface TakhmeenEntry {
  its_id: string;                      // Required: Must exist in census table
  amount: number;                      // Required: Minimum 0
  currency?: string;                    // Optional: 3-character code (default: "LKR")
  conversion_rate?: number;            // Optional: Minimum 0.000001 (default: 1.0)
}
```

**Validation Rules:**
- `miqaat_id`: Required, integer, must exist in `miqaats` table
- `entries`: Required, array, 1-1000 items
- `entries.*.its_id`: Required, string, must exist in `census` table
- `entries.*.amount`: Required, number, minimum 0
- `entries.*.currency`: Optional, string, exactly 3 characters
- `entries.*.conversion_rate`: Optional, number, minimum 0.000001
- `its_id`: Optional, string, must exist in `census` table

**Response (201 Created):**
```typescript
interface TakhmeenStoreResponse {
  success: true;
  data: {
    saved: Wajebaat[];                 // Array of saved wajebaat records
    group?: GroupData | null;          // Present only if its_id was provided and member belongs to a group
  };
}
```

**Response (422 Unprocessable Entity):**
```typescript
interface ValidationErrorResponse {
  success: false;
  error: "VALIDATION_ERROR";
  message: string;                      // First validation error message
}
```

**Business Logic:**
1. For each entry, the system checks if the `its_id` belongs to a `wajebaat_groups` and automatically links `wg_id`
2. Amount is stored in the currency provided (no conversion at write-time)
3. `conversion_rate` is stored for reporting purposes only and does not affect the stored amount
4. Category (`wc_id`) is automatically assigned based on amount slabs for the miqaat
5. If optional `its_id` is provided, the response includes all group members' data

**Example Request:**
```json
{
  "miqaat_id": 1,
  "entries": [
    {
      "its_id": "123456",
      "amount": 5000.00,
      "currency": "LKR",
      "conversion_rate": 1.0
    },
    {
      "its_id": "789012",
      "amount": 7500.00,
      "currency": "LKR"
    }
  ],
  "its_id": "123456"
}
```

**Example Response (201):**
```json
{
  "success": true,
  "data": {
    "saved": [
      {
        "id": 1,
        "miqaat_id": 1,
        "its_id": "123456",
        "wg_id": 5,
        "amount": "5000.00",
        "currency": "LKR",
        "conversion_rate": "1.000000",
        "status": false,
        "wc_id": 2,
        "created_at": "2026-01-28T12:00:00.000000Z",
        "updated_at": "2026-01-28T12:00:00.000000Z"
      },
      {
        "id": 2,
        "miqaat_id": 1,
        "its_id": "789012",
        "wg_id": null,
        "amount": "7500.00",
        "currency": "LKR",
        "conversion_rate": "1.000000",
        "status": false,
        "wc_id": 3,
        "created_at": "2026-01-28T12:00:00.000000Z",
        "updated_at": "2026-01-28T12:00:00.000000Z"
      }
    ],
    "group": {
      "wg_id": 5,
      "master_its": "123456",
      "members": [
        {
          "its_id": "123456",
          "person": {
            "its_id": "123456",
            "name": "John Doe",
            "hof_id": "123456",
            // ... other census fields
          },
          "wajebaat": {
            "id": 1,
            "amount": "5000.00",
            "status": false,
            // ... other wajebaat fields
          }
        },
        {
          "its_id": "111222",
          "person": { /* ... */ },
          "wajebaat": { /* ... */ }
        }
      ]
    }
  }
}
```

---

### 2. Finance Ada Update

**Endpoint:** `PATCH /api/miqaats/{miqaat_id}/wajebaat/{its_id}/paid`

**Description:**  
Mark a member's wajebaat as paid or unpaid for a specific miqaat. Before saving `paid=true`, the controller queries the `miqaat_checks` table. If any check for that `its_id` is false (or missing), returns 403 Forbidden with a JSON payload listing the specific `mcd_id` names that are pending.

**URL Parameters:**
- `miqaat_id` (path, required): Integer, must exist in `miqaats` table
- `its_id` (path, required): String, must exist in `census` table

**Request Headers:**
```
Content-Type: application/json
```

**Request Body:**
```typescript
interface FinanceAdaUpdateRequest {
  paid: boolean;                       // Required: Payment status
}
```

**Validation Rules:**
- `miqaat_id`: Required, integer, must exist in `miqaats` table
- `its_id`: Required, string, must exist in `census` table
- `paid`: Required, boolean

**Response (200 OK):**
```typescript
interface FinanceAdaUpdateResponse {
  success: true;
  data: Wajebaat;                      // Updated wajebaat record
}
```

**Response (403 Forbidden - Department Checks Pending):**
```typescript
interface DepartmentChecksPendingResponse {
  success: false;
  error: "DEPARTMENT_CHECKS_PENDING";
  message: "Cannot mark as paid: department checks are pending.";
  pending_departments: PendingDepartment[];
}
```

**Response (422 Unprocessable Entity):**
```typescript
interface ValidationErrorResponse {
  success: false;
  error: "VALIDATION_ERROR";
  message: string;
}
```

**Business Logic:**
1. **Department Guard**: When `paid=true`, the system checks all departments in `miqaat_check_departments`
2. A department is considered **pending** if:
   - There is no `miqaat_checks` row for that department, OR
   - The `miqaat_checks` row exists but `is_cleared=false`
3. If any department is pending, the request is blocked with 403 response
4. The 403 response includes all pending departments with their `mcd_id` and `name`
5. Currency is already stored in the wajebaat record; no currency conversion is performed

**Example Request:**
```json
{
  "paid": true
}
```

**Example Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "miqaat_id": 1,
    "its_id": "123456",
    "wg_id": 5,
    "amount": "5000.00",
    "currency": "LKR",
    "conversion_rate": "1.000000",
    "status": true,
    "wc_id": 2,
    "created_at": "2026-01-28T12:00:00.000000Z",
    "updated_at": "2026-01-28T12:05:00.000000Z"
  }
}
```

**Example Response (403):**
```json
{
  "success": false,
  "error": "DEPARTMENT_CHECKS_PENDING",
  "message": "Cannot mark as paid: department checks are pending.",
  "pending_departments": [
    {
      "mcd_id": 1,
      "name": "Finance"
    },
    {
      "mcd_id": 3,
      "name": "Clearance"
    }
  ]
}
```

---

## Business Logic Rules

### 1. Group Membership Detection

- When saving Takhmeen entries, the system automatically checks if each `its_id` belongs to a `wajebaat_groups`
- If a member belongs to a group, the `wg_id` is automatically linked to the wajebaat record
- Groups are identified by `(miqaat_id, wg_id)` combination
- A group has one `master_its` and multiple member `its_id` values

### 2. Automatic Categorization

- After saving a wajebaat record, the system automatically assigns a category (`wc_id`) based on:
  - The `miqaat_id` (categories are scoped per miqaat)
  - The `amount` value
- Categories are defined in `waj_categories` table with `low_bar` and `upper_bar` (nullable for no upper limit)
- The system finds the matching category where `low_bar <= amount <= upper_bar` (or `upper_bar IS NULL`)

### 3. Currency Handling

- **Storage**: Amounts are stored in the currency provided (no conversion at write-time)
- **Conversion Rate**: `conversion_rate` is stored for reporting purposes only
- **Payment Processing**: When updating payment status, the currency already stored in the wajebaat record is used
- **Default Currency**: If not provided, defaults to "LKR"
- **Default Conversion Rate**: If not provided, defaults to 1.0

### 4. Department Guard

- **Trigger**: Only when `paid=true` is being set
- **Check Process**:
  1. Query all departments from `miqaat_check_departments`
  2. For each department, check if a `miqaat_checks` row exists for `(miqaat_id, its_id, mcd_id)`
  3. If no check exists OR `is_cleared=false`, the department is pending
- **Blocking**: If any department is pending, the payment update is blocked with 403 response
- **Response**: 403 response includes all pending departments with `mcd_id` and `name`

### 5. Group Data Retrieval

- If optional `its_id` is provided in Takhmeen Store request:
  - System checks if that ITS belongs to a `wajebaat_groups`
  - If yes, returns all members in that group with their:
    - Census records (`person`)
    - Wajebaat records (`wajebaat`)
  - If no group membership, `group` field is `null` or omitted

---

## Error Handling

### Standard Error Response Format

All error responses follow this structure:

```typescript
interface ErrorResponse {
  success: false;
  error: string;                      // Error code (e.g., "VALIDATION_ERROR", "DEPARTMENT_CHECKS_PENDING")
  message: string;                    // Human-readable error message
}
```

### HTTP Status Codes

- **200 OK**: Successful operation
- **201 Created**: Resource created successfully
- **403 Forbidden**: Department checks pending (Finance Ada Update only)
- **404 Not Found**: Resource not found
- **422 Unprocessable Entity**: Validation error

### Common Error Codes

| Error Code | Description | Status Code |
|-----------|-------------|-------------|
| `VALIDATION_ERROR` | Request validation failed | 422 |
| `DEPARTMENT_CHECKS_PENDING` | Cannot mark as paid: department checks are pending | 403 |

### Validation Error Examples

**Missing Required Field:**
```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "The miqaat_id field is required."
}
```

**Invalid Data Type:**
```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "The entries must be an array."
}
```

**Invalid Reference:**
```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "The selected miqaat_id is invalid."
}
```

---

## Examples

### Example 1: Bulk Save Takhmeen (No Group Lookup)

**Request:**
```http
POST /api/wajebaat/takhmeen
Content-Type: application/json

{
  "miqaat_id": 1,
  "entries": [
    {
      "its_id": "123456",
      "amount": 5000.00,
      "currency": "LKR"
    },
    {
      "its_id": "789012",
      "amount": 7500.00,
      "currency": "USD",
      "conversion_rate": 0.003
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "saved": [
      {
        "id": 1,
        "miqaat_id": 1,
        "its_id": "123456",
        "wg_id": null,
        "amount": "5000.00",
        "currency": "LKR",
        "conversion_rate": "1.000000",
        "status": false,
        "wc_id": 2,
        "created_at": "2026-01-28T12:00:00.000000Z",
        "updated_at": "2026-01-28T12:00:00.000000Z"
      },
      {
        "id": 2,
        "miqaat_id": 1,
        "its_id": "789012",
        "wg_id": null,
        "amount": "7500.00",
        "currency": "USD",
        "conversion_rate": "0.003000",
        "status": false,
        "wc_id": 3,
        "created_at": "2026-01-28T12:00:00.000000Z",
        "updated_at": "2026-01-28T12:00:00.000000Z"
      }
    ]
  }
}
```

### Example 2: Bulk Save with Group Lookup

**Request:**
```http
POST /api/wajebaat/takhmeen
Content-Type: application/json

{
  "miqaat_id": 1,
  "entries": [
    {
      "its_id": "123456",
      "amount": 5000.00
    }
  ],
  "its_id": "123456"
}
```

**Response (with group data):**
```json
{
  "success": true,
  "data": {
    "saved": [
      {
        "id": 1,
        "miqaat_id": 1,
        "its_id": "123456",
        "wg_id": 5,
        "amount": "5000.00",
        "currency": "LKR",
        "conversion_rate": "1.000000",
        "status": false,
        "wc_id": 2,
        "created_at": "2026-01-28T12:00:00.000000Z",
        "updated_at": "2026-01-28T12:00:00.000000Z"
      }
    ],
    "group": {
      "wg_id": 5,
      "master_its": "123456",
      "members": [
        {
          "its_id": "123456",
          "person": { /* census data */ },
          "wajebaat": { /* wajebaat data */ }
        },
        {
          "its_id": "111222",
          "person": { /* census data */ },
          "wajebaat": null
        }
      ]
    }
  }
}
```

### Example 3: Mark as Paid (Success)

**Request:**
```http
PATCH /api/miqaats/1/wajebaat/123456/paid
Content-Type: application/json

{
  "paid": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "miqaat_id": 1,
    "its_id": "123456",
    "wg_id": 5,
    "amount": "5000.00",
    "currency": "LKR",
    "conversion_rate": "1.000000",
    "status": true,
    "wc_id": 2,
    "created_at": "2026-01-28T12:00:00.000000Z",
    "updated_at": "2026-01-28T12:05:00.000000Z"
  }
}
```

### Example 4: Mark as Paid (Department Checks Pending)

**Request:**
```http
PATCH /api/miqaats/1/wajebaat/123456/paid
Content-Type: application/json

{
  "paid": true
}
```

**Response (403):**
```json
{
  "success": false,
  "error": "DEPARTMENT_CHECKS_PENDING",
  "message": "Cannot mark as paid: department checks are pending.",
  "pending_departments": [
    {
      "mcd_id": 1,
      "name": "Finance"
    },
    {
      "mcd_id": 3,
      "name": "Clearance"
    }
  ]
}
```

### Example 5: Mark as Unpaid

**Request:**
```http
PATCH /api/miqaats/1/wajebaat/123456/paid
Content-Type: application/json

{
  "paid": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "miqaat_id": 1,
    "its_id": "123456",
    "wg_id": 5,
    "amount": "5000.00",
    "currency": "LKR",
    "conversion_rate": "1.000000",
    "status": false,
    "wc_id": 2,
    "created_at": "2026-01-28T12:00:00.000000Z",
    "updated_at": "2026-01-28T12:10:00.000000Z"
  }
}
```

---

## Frontend Integration Notes

### 1. TypeScript Interfaces

Use the TypeScript interfaces provided in this document to type your API calls and responses.

### 2. Error Handling

Always check the `success` field in responses:
- `success: true` → Handle data
- `success: false` → Handle error (check `error` code and `message`)

### 3. Department Guard UI

When marking as paid:
- If you receive a 403 response with `DEPARTMENT_CHECKS_PENDING`:
  - Display the `pending_departments` list to the user
  - Show which departments need to be cleared before payment can be marked
  - Provide a link/action to manage department checks

### 4. Group Data Display

When using Takhmeen Store with `its_id`:
- Check if `data.group` exists in the response
- If present, display all group members with their wajebaat status
- Use this to show group-level wajebaat information

### 5. Currency Display

- Always display amounts with their currency code
- Use `conversion_rate` only for reporting/display purposes (e.g., showing equivalent in base currency)
- Do not perform currency conversion on the frontend for payment processing

### 6. Bulk Operations

- The Takhmeen Store endpoint supports 1-1000 entries per request
- For larger datasets, split into multiple requests
- Show progress indicators for bulk operations

### 7. Validation

- Validate all required fields before sending requests
- Handle validation errors gracefully with user-friendly messages
- Use the validation rules specified in this document

---

## Changelog

### Version 1.0.0 (January 28, 2026)
- Initial release
- Takhmeen Store endpoint
- Finance Ada Update endpoint
- Department Guard implementation
- Currency support
- Group membership detection

---

## Support

For questions or issues with this API specification, please contact the backend development team.

**API Base URL:** `/api`  
**Documentation Version:** 1.0.0
