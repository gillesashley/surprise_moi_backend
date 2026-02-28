# Reviews API (v1)

Base path: `/api/v1`

All responses use:

```json
{
  "success": true,
  "message": "string",
  "data": {}
}
```

Validation errors return:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["..."]
  }
}
```

## Review Object

```json
{
  "id": 1,
  "user_id": 10,
  "user_name": "Jane Doe",
  "user_avatar": "https://example.com/storage/avatars/a.jpg",
  "item_name": "Birthday Cake",
  "item_id": 33,
  "item_type": "product",
  "order_id": 55,
  "rating": 4.5,
  "comment": "Great quality",
  "images": [
    "https://example.com/storage/reviews/images/x.jpg"
  ],
  "helpful_count": 2,
  "is_helpful_by_me": true,
  "is_verified_purchase": true,
  "created_at": "2026-02-28T12:00:00.000000Z",
  "updated_at": "2026-02-28T12:00:00.000000Z"
}
```

## Endpoints

### `POST /reviews` (auth)

Multipart/form-data fields:
- `item_id` (int, required)
- `item_type` (`product|service`, required)
- `order_id` (int, optional)
- `rating` (float, 1.0-5.0, step 0.5)
- `comment` (string, optional)
- `images[]` (image files, optional, max 5)

Success:

```json
{
  "success": true,
  "message": "Review submitted successfully.",
  "data": {
    "review": {}
  }
}
```

### `GET /reviews`

Query:
- `item_id` (required)
- `item_type` (required)
- `page` (optional)
- `per_page` (optional)

Success:

```json
{
  "success": true,
  "message": "Reviews retrieved successfully.",
  "data": {
    "reviews": [],
    "average_rating": 4.4,
    "total_reviews": 20,
    "rating_distribution": {
      "1": 1,
      "2": 1,
      "3": 3,
      "4": 5,
      "5": 10
    },
    "current_page": 1,
    "last_page": 2
  }
}
```

### `GET /reviews/{id}`

Success:

```json
{
  "success": true,
  "message": "Review retrieved successfully.",
  "data": {}
}
```

### `PUT /reviews/{id}` (auth owner)

Same payload contract as create.

Success:

```json
{
  "success": true,
  "message": "Review updated successfully.",
  "data": {
    "review": {}
  }
}
```

### `DELETE /reviews/{id}` (auth owner)

```json
{
  "success": true,
  "message": "Review deleted successfully.",
  "data": null
}
```

### `POST /reviews/{id}/helpful` (auth)

Toggles helpful state.

```json
{
  "success": true,
  "message": "Review marked as helpful.",
  "data": {
    "review_id": 1,
    "is_helpful_by_me": true,
    "helpful_count": 3
  }
}
```

### `GET /vendor/reviews` (auth vendor)

Query:
- `page`, `per_page`
- `rating`
- `has_images` (`0|1`)
- `start_date`, `end_date`
- `search`

```json
{
  "success": true,
  "message": "Vendor reviews retrieved successfully.",
  "data": {
    "reviews": [],
    "average_rating": 4.6,
    "total_reviews": 9,
    "rating_distribution": {
      "1": 0,
      "2": 1,
      "3": 1,
      "4": 3,
      "5": 4
    },
    "current_page": 1,
    "last_page": 1
  }
}
```

### `POST /reviews/{id}/replies` (auth vendor owner)

```json
{
  "message": "Thank you for your review."
}
```

### `GET /reviews/{id}/replies`

Returns reply list (0..1).

### `PUT /review-replies/{id}` (auth vendor owner)

```json
{
  "message": "Updated vendor reply."
}
```

### `DELETE /review-replies/{id}` (auth vendor owner)

```json
{
  "success": true,
  "message": "Reply deleted successfully.",
  "data": null
}
```
