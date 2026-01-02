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

## 3. Get Favorite Products with Store Totals

**URL:** `GET /api/favorite/products`

**Authentication:** Required (Bearer token)

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (With Favorites):**
```json
{
  "products": [
    {
      "id": 123,
      "store_id": 1,
      "original_price": 10.99,
      "discounted_price": 8.99,
      "discount_percent": 18,
      "condition": null,
      "info": null,
      "card": false,
      "from_date": "2025-12-01",
      "to_date": "2025-12-31",
      "valid_date": "2025-12-01 - 2025-12-31",
      "offer_count": 2,
      "min_price": 8.99,
      "offers": [...],
      "history": [...],
      "product": {
        "id": 33935,
        "name": "Product Name",
        "slug": "product-slug",
        "brand": "Brand Name",
        "full_slug": "category-slug/product-slug",
        "category_id": 5,
        "image_url": "https://...",
        "category": {
          "id": 5,
          "name": "Category Name",
          "slug": "category-slug"
        }
      }
    }
  ],
  "store_totals": [
    {
      "store_id": 1,
      "store_name": "Rimi",
      "total_price": 45.99,
      "product_count": 3
    },
    {
      "store_id": 2,
      "store_name": "Maxima",
      "total_price": 38.50,
      "product_count": 2
    }
  ]
}
```

**Response (No Favorites):**
```json
{
  "products": [],
  "store_totals": []
}
```

**Error Response (401 - Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

## Notes

- All endpoints require authentication via Sanctum Bearer token
- Favorites list is ordered by `created_at` DESC (newest first)
- `toggleFavorite` returns the complete updated favorites list after each operation
- Product IDs in the favorites array are integers
- `store_totals` shows the total cost of all favorite products per store (using minimum discounted_price if product has multiple discounts in same store)
- `product_count` in `store_totals` indicates how many favorite products are available in that store

