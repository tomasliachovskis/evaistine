# Favorites API Documentation

## 1. Toggle Favorite (Add/Remove)

**URL:** `POST /api/favorite/product`

**Authentication:** Required (Bearer token)

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "product_id": 33935
}
```

**Response (Product Added):**
```json
{
  "status": "added",
  "favorites": [33935, 12345, 67890]
}
```

**Response (Product Removed):**
```json
{
  "status": "removed",
  "favorites": [12345, 67890]
}
```

**Response (Empty Favorites):**
```json
{
  "status": "removed",
  "favorites": []
}
```

**Error Response (422 - Validation Failed):**
```json
{
  "error": "Validation failed",
  "messages": {
    "product_id": ["The product id field is required."]
  }
}
```

**Error Response (401 - Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

## 2. Get Favorites List

**URL:** `GET /api/favorite/list`

**Authentication:** Required (Bearer token)

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (With Favorites):**
```json
[33935, 12345, 67890, 45678]
```

**Response (No Favorites):**
```json
[]
```

**Error Response (401 - Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

## Notes

- Both endpoints require authentication via Sanctum Bearer token
- Favorites list is ordered by `created_at` DESC (newest first)
- `toggleFavorite` returns the complete updated favorites list after each operation
- Product IDs in the favorites array are integers

