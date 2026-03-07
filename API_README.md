# AtndSaaS Backend API Documentation

This document provides a comprehensive list of all RESTful API endpoints exposed by the Laravel backend. The API handles device provisioning, face recognition syncing, attendance event pushing, and React SPA admin traffic.

All endpoints are prefixed with `/api`.

---

## 1. Public / Unauthenticated Endpoints

### `GET /api/ping`
- **Description:** Health check.
- **Returns:** `{ "status": "ok" }`

### `POST /api/device/register`
- **Description:** Phase-1 tablet provisioning. Registers a new tablet against a tenant using the tenant's domain and a pre-generated device API key.
- **Request Body:**
  ```json
  {
    "domain": "string",
    "api_key": "string"
  }
  ```
- **Returns (200):** A Bearer token for the device, and the tenant's visual settings.
  ```json
  {
    "token": "1|abcdef...",
    "device": { "id": 1, "name": "Front Desk Tablet", "tenant_id": 1 },
    "tenant": { "id": 1, "name": "Demo School", "settings": { "primary_color": "#FFC0CB" } },
    "entity_types": [ { "id": 1, "name": "Grade", "is_required": true } ]
  }
  ```

### `POST /api/auth/login`
- **Description:** Legacy device login to get a new token via just the API key.
- **Request Body:** `{ "api_key": "string" }`
- **Returns (200):** `{ "token": "...", "device": {...} }`

### `POST /api/admin/login`
- **Description:** Admin and SuperAdmin login endpoint for the React SPA.
- **Request Body:**
  ```json
  {
    "email": "user@example.com", // Omit for default Super Admin login
    "password": "password123"
  }
  ```
- **Returns (200):** `{ "token": "...", "user": { "id": 1, "name": "...", "role": "admin|organisation", "tenant_id": 1 } }`

---

## 2. Authenticated Device Endpoints
*Requires: `Authorization: Bearer <device_token>`*

### `POST /api/auth/logout`
- **Description:** Revokes the device's current Bearer token.

### `GET /api/face/users`
- **Description:** Returns all users belonging to the device's tenant, specifically for the Face Enrollment dropdown UI.
- **Returns (200):** Array of users including enrollment status.
  ```json
  [
    {
      "id": 1,
      "name": "Jane Doe",
      "employee_id": "EMP-001",
      "profile_picture_url": "https://...",
      "has_embedding": true,
      "entities": [{"type": "Grade", "value": "10th"}]
    }
  ]
  ```

### `GET /api/face/embeddings`
- **Description:** Incremental sync endpoint. Downloads face embeddings for the local on-device Hive cache.
- **Query Params:** `updated_after` (optional ISO8601 dates to fetch only changes).
- **Returns (200):** Array of base64 encoded embeddings.

### `POST /api/face/enroll`
- **Description:** Registers a new face embedding for a specific user.
- **Request Body:**
  ```json
  {
    "user_id": 1,
    "embedding": "base64Encoded2048ByteFloat32List",
    "model_version": "w600k_mbf"
  }
  ```
- **Returns (201):** `{ "message": "Enrolled." }`

### `POST /api/face/match`
- **Description:** Server-side fallback for face matching (cosine similarity).
- **Request Body:** `{ "embedding": "base64..." }`
- **Returns (200):** `{ "matched": true, "user_id": 1, "similarity": 0.54 }`

### `POST /api/attendance/check-in` & `POST /api/attendance/check-out`
- **Description:** Single event trigger.
- **Request Body:** `{ "user_id": 1, "recorded_at": "2024-10-10T14:30:00Z", "similarity": 0.65 }`

### `POST /api/attendance/sync`
- **Description:** Batch upload for offline attendance queues. Device pushes records.
- **Request Body:**
  ```json
  {
    "records": [
      { "local_id": "uuid", "user_id": 1, "type": "check_in", "recorded_at": "ISO8601", "similarity": 0.55 }
    ]
  }
  ```
- **Returns (200):** Lists of successfully stored vs failed local IDs.

### `GET /api/attendance/sync`
- **Description:** Bidirectional sync for devices. Device downloads records captured by *other* terminals.
- **Query Params:** `since` (optional ISO8601), `limit` (int, max 1000).

---

## 3. Authenticated Admin Endpoints (Organization Admin)
*Requires: `Authorization: Bearer <admin_token>`, `role = organisation`*

### `POST /api/admin/logout`
- **Description:** Revokes administrative token.

### `GET /api/admin/users`
- **Description:** Paginated list of users in the tenant.
- **Query Params:** `search`, `entity_id`, `entity_type_id`, `per_page`.

### `POST /api/admin/users`
- **Description:** Create a new user (employee/student).
- **Request Body:** `{ "name": "John", "email": "john@ex.com", "employee_id": "J123", "role": "user" }`

### `GET | PUT | DELETE /api/admin/users/{id}`
- **Description:** Inspect, Modify, or Remove a user.

### `POST /api/admin/users/{id}/entities`
- **Description:** Sync taxonomy assignments (e.g., assigning User to "Grade 10").
- **Request Body:** `{ "entity_ids": [1, 2, 5] }`

### Taxonomy Management Routes
- `GET /api/admin/entity-types`: List structure definitions (e.g., "Department", "Role").
- `POST /api/admin/entity-types`: Create a structure definition.
- `DELETE /api/admin/entity-types/{id}`: Delete a definition.
- `GET /api/admin/entity-types/{id}/entities`: List options inside a definition (e.g., "HR", "Sales" inside "Department").
- `POST /api/admin/entity-types/{id}/entities`: Create a new option.
- `DELETE /api/admin/entities/{id}`: Delete an option.

### `GET /api/reports/attendance`
- **Description:** Generates offline reporting views.
- **Query Params:** `user_id`, `start_date`, `end_date`, `entity_id`, `entity_type_id`, `format` (json | csv).
- **Returns:** Paginated JSON logs or a downloadable `.csv` file attachment.

---

## 4. Super Admin Endpoints (Platform Admin)
*Requires: `Authorization: Bearer <admin_token>`, `role = admin`*

### `GET /api/super-admin/tenants`
- **Description:** Lists all SaaS organizations.

### `POST /api/super-admin/tenants`
- **Description:** Provisions a new SaaS tenant, seeds their DB, and creates their Org Admin.
- **Request Body:**
  ```json
  {
    "name": "GymCorp",
    "domain": "gymcorp.com",
    "industry": "fitness",
    "admin_name": "Gym Admin",
    "admin_email": "admin@gymcorp.com",
    "admin_password": "securepassword"
  }
  ```

### `GET | DELETE /api/super-admin/tenants/{id}`
- **Description:** View or Destroy a SaaS tenant. Does cascading deletes.
