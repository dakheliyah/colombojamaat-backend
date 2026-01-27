# Census API Documentation

This document provides comprehensive API documentation for all endpoints related to the `census` table.

## Base URL
All endpoints are prefixed with your API base URL (e.g., `/api` or `/`).

---

## 1. Get Census Record by ITS ID

**Endpoint:** `GET /census/{its_id}`

**Description:** Retrieves a single census record by ITS ID.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `its_id` | string | Yes | ITS ID of the person |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "its_id": "123456",
    "hof_id": "123456",
    "father_its": "111111",
    "mother_its": "222222",
    "spouse_its": "654321",
    "sabeel": "ABC123",
    "name": "John Doe",
    "arabic_name": "جون دو",
    "age": 35,
    "gender": "male",
    "misaq": "yes",
    "marital_status": "married",
    "blood_group": "O+",
    "mobile": "1234567890",
    "email": "john.doe@example.com",
    "address": "123 Main Street",
    "city": "Mumbai",
    "pincode": "400001",
    "mohalla": "Downtown",
    "area": "South",
    "jamaat": "Mumbai Jamaat",
    "jamiat": "Maharashtra Jamiat",
    "pwd": null,
    "synced": "yes",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

### Error Response (404 Not Found)

```json
{
  "error": "NOT_FOUND",
  "message": "Census record not found."
}
```

---

## 2. Get Census Record with Family Relationships

**Endpoint:** `GET /census/{its_id}/with-relations`

**Description:** Retrieves a census record with its Head of Family (HOF) and family members relationships.

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `its_id` | string | Yes | ITS ID of the person |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "its_id": "123456",
    "hof_id": "123456",
    "father_its": "111111",
    "mother_its": "222222",
    "spouse_its": "654321",
    "sabeel": "ABC123",
    "name": "John Doe",
    "arabic_name": "جون دو",
    "age": 35,
    "gender": "male",
    "misaq": "yes",
    "marital_status": "married",
    "blood_group": "O+",
    "mobile": "1234567890",
    "email": "john.doe@example.com",
    "address": "123 Main Street",
    "city": "Mumbai",
    "pincode": "400001",
    "mohalla": "Downtown",
    "area": "South",
    "jamaat": "Mumbai Jamaat",
    "jamiat": "Maharashtra Jamiat",
    "pwd": null,
    "synced": "yes",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "hof": {
      "id": 1,
      "its_id": "123456",
      "name": "John Doe",
      "age": 35,
      "gender": "male"
    },
    "members": [
      {
        "id": 2,
        "its_id": "789012",
        "hof_id": "123456",
        "name": "Jane Doe",
        "age": 30,
        "gender": "female",
        "relationship": "spouse"
      },
      {
        "id": 3,
        "its_id": "345678",
        "hof_id": "123456",
        "name": "Child Doe",
        "age": 10,
        "gender": "male",
        "relationship": "child"
      }
    ]
  }
}
```

### Error Response (404 Not Found)

```json
{
  "error": "NOT_FOUND",
  "message": "Census record not found."
}
```

---

## 3. Get Family Members by HOF ITS ID

**Endpoint:** `GET /census/family/{hof_its}`

**Description:** Retrieves all family members for a given Head of Family (HOF) ITS ID. The HOF is included as the first record, followed by all family members ordered by age (descending).

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hof_its` | string | Yes | ITS ID of the Head of Family |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "its_id": "123456",
      "hof_id": "123456",
      "name": "John Doe",
      "arabic_name": "جون دو",
      "age": 35,
      "gender": "male",
      "misaq": "yes",
      "marital_status": "married",
      "blood_group": "O+",
      "mobile": "1234567890",
      "email": "john.doe@example.com",
      "address": "123 Main Street",
      "city": "Mumbai",
      "pincode": "400001",
      "mohalla": "Downtown",
      "area": "South",
      "jamaat": "Mumbai Jamaat",
      "jamiat": "Maharashtra Jamiat",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    {
      "id": 2,
      "its_id": "789012",
      "hof_id": "123456",
      "name": "Jane Doe",
      "arabic_name": "جين دو",
      "age": 30,
      "gender": "female",
      "misaq": "yes",
      "marital_status": "married",
      "blood_group": "A+",
      "mobile": "0987654321",
      "email": "jane.doe@example.com",
      "address": "123 Main Street",
      "city": "Mumbai",
      "pincode": "400001",
      "mohalla": "Downtown",
      "area": "South",
      "jamaat": "Mumbai Jamaat",
      "jamiat": "Maharashtra Jamiat",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    {
      "id": 3,
      "its_id": "345678",
      "hof_id": "123456",
      "name": "Child Doe",
      "arabic_name": "طفل دو",
      "age": 10,
      "gender": "male",
      "misaq": "no",
      "marital_status": "single",
      "blood_group": "O+",
      "mobile": null,
      "email": null,
      "address": "123 Main Street",
      "city": "Mumbai",
      "pincode": "400001",
      "mohalla": "Downtown",
      "area": "South",
      "jamaat": "Mumbai Jamaat",
      "jamiat": "Maharashtra Jamiat",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

### Error Response (404 Not Found)

```json
{
  "error": "NOT_FOUND",
  "message": "Head of Family not found."
}
```

---

## 4. List All Census Records

**Endpoint:** `GET /census`

**Description:** Retrieves all census records with pagination support.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1, minimum: 1) |
| `per_page` | integer | No | Number of records per page (default: 15, minimum: 1, maximum: 100) |

### Request Example

```
GET /census?page=1&per_page=20
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "its_id": "123456",
        "hof_id": "123456",
        "name": "John Doe",
        "arabic_name": "جون دو",
        "age": 35,
        "gender": "male",
        "city": "Mumbai",
        "jamaat": "Mumbai Jamaat",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
      },
      {
        "id": 2,
        "its_id": "789012",
        "hof_id": "123456",
        "name": "Jane Doe",
        "arabic_name": "جين دو",
        "age": 30,
        "gender": "female",
        "city": "Mumbai",
        "jamaat": "Mumbai Jamaat",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 1000,
      "last_page": 67,
      "from": 1,
      "to": 15
    }
  }
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The per page must be between 1 and 100."
}
```

---

## 5. Search Census Records

**Endpoint:** `GET /census/search`

**Description:** Searches and filters census records based on various criteria with pagination support.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | No | Search by name (searches both `name` and `arabic_name` fields) |
| `its_id` | string | No | Filter by exact ITS ID |
| `hof_id` | string | No | Filter by Head of Family ITS ID |
| `city` | string | No | Filter by city (partial match) |
| `jamaat` | string | No | Filter by jamaat (partial match) |
| `jamiat` | string | No | Filter by jamiat (partial match) |
| `mohalla` | string | No | Filter by mohalla (partial match) |
| `area` | string | No | Filter by area (partial match) |
| `gender` | string | No | Filter by gender. Options: `male`, `female` |
| `misaq` | string | No | Filter by misaq status |
| `marital_status` | string | No | Filter by marital status |
| `page` | integer | No | Page number (default: 1, minimum: 1) |
| `per_page` | integer | No | Number of records per page (default: 15, minimum: 1, maximum: 100) |

### Request Examples

**Search by name:**
```
GET /census/search?name=John
```

**Filter by city and jamaat:**
```
GET /census/search?city=Mumbai&jamaat=Mumbai%20Jamaat
```

**Filter by HOF and pagination:**
```
GET /census/search?hof_id=123456&page=1&per_page=20
```

**Multiple filters:**
```
GET /census/search?city=Mumbai&gender=male&misaq=yes&page=1&per_page=25
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "its_id": "123456",
        "hof_id": "123456",
        "name": "John Doe",
        "arabic_name": "جون دو",
        "age": 35,
        "gender": "male",
        "misaq": "yes",
        "marital_status": "married",
        "city": "Mumbai",
        "jamaat": "Mumbai Jamaat",
        "jamiat": "Maharashtra Jamiat",
        "mohalla": "Downtown",
        "area": "South",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 1,
      "last_page": 1,
      "from": 1,
      "to": 1
    }
  }
}
```

### Error Response (422 Unprocessable Entity)

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The gender must be one of: male, female."
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
  "error": "NOT_FOUND",
  "message": "Census record not found."
}
```

---

## Census Table Fields

The census table contains the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key (auto-increment) |
| `its_id` | string | Unique ITS ID of the person |
| `hof_id` | string | ITS ID of the Head of Family |
| `father_its` | string | ITS ID of the father (nullable) |
| `mother_its` | string | ITS ID of the mother (nullable) |
| `spouse_its` | string | ITS ID of the spouse (nullable) |
| `sabeel` | string | Sabeel number (nullable) |
| `name` | string | Name in English (nullable) |
| `arabic_name` | string | Name in Arabic (nullable) |
| `age` | integer | Age (nullable) |
| `gender` | string | Gender (nullable) |
| `misaq` | string | Misaq status (nullable) |
| `marital_status` | string | Marital status (nullable) |
| `blood_group` | string | Blood group (nullable) |
| `mobile` | string | Mobile number (nullable) |
| `email` | string | Email address (nullable) |
| `address` | text | Address (nullable) |
| `city` | string | City (nullable) |
| `pincode` | string | Pincode (nullable) |
| `mohalla` | string | Mohalla (nullable) |
| `area` | string | Area (nullable) |
| `jamaat` | string | Jamaat (nullable) |
| `jamiat` | string | Jamiat (nullable) |
| `pwd` | string | PWD status (nullable) |
| `synced` | string | Sync status (nullable) |
| `created_at` | timestamp | Record creation timestamp |
| `updated_at` | timestamp | Record last update timestamp |

---

## Notes

1. **ITS ID Uniqueness**: The `its_id` field is unique in the census table. Each person has a unique ITS ID.

2. **Head of Family (HOF)**: The `hof_id` field references the ITS ID of the Head of Family. A person can be their own HOF (where `its_id = hof_id`).

3. **Family Relationships**: 
   - To get all members of a family, use the `GET /census/family/{hof_its}` endpoint
   - The HOF is always included as the first record in the family members list
   - Family members are ordered by age (descending)

4. **Search Functionality**:
   - The `name` parameter searches both `name` and `arabic_name` fields using partial matching
   - All string filters use partial matching (LIKE queries)
   - Multiple filters can be combined using query parameters

5. **Pagination**:
   - Default page size is 15 records
   - Maximum page size is 100 records
   - Pagination metadata is included in the response for list and search endpoints

6. **Ordering**:
   - List endpoint orders records by name (ascending)
   - Search endpoint orders records by name (ascending)
   - Family members endpoint orders by HOF first, then by age (descending)

7. **Relationships**:
   - Use `GET /census/{its_id}/with-relations` to get a person with their HOF and family members
   - The `hof` relationship returns the Head of Family record
   - The `members` relationship returns all family members (where `hof_id` matches the person's `its_id`)

---

## Example Use Cases

### 1. Find a person by ITS ID
```
GET /census/123456
```

### 2. Get all family members for a HOF
```
GET /census/family/123456
```

### 3. Search for people in Mumbai
```
GET /census/search?city=Mumbai
```

### 4. Find all males with misaq in a specific jamaat
```
GET /census/search?gender=male&misaq=yes&jamaat=Mumbai%20Jamaat
```

### 5. Get all members of a specific family with pagination
```
GET /census/search?hof_id=123456&page=1&per_page=50
```

### 6. Search by name (searches both English and Arabic names)
```
GET /census/search?name=John
```

### 7. Get person with family relationships
```
GET /census/123456/with-relations
```
