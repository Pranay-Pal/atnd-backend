# AtndSaaS Backend API Documentation

This document provides a comprehensive and strictly verified list of all RESTful API endpoints exposed by the Laravel backend. The API handles device provisioning, face recognition syncing, attendance event pushing, and React SPA admin traffic.

All endpoints are prefixed with `/api`.

---

## 1. Public / Unauthenticated Endpoints

### `GET /api/ping`
- **Description:** Basic health check.
- **Returns (200):** `{ "status": "ok" }`

### `POST /api/device/register`
- **Description:** Phase-1 tablet provisioning. Registers a new tablet against a tenant using the tenant's domain and a pre-generated device API key.
- **Request Body:**
  ```json
  {
    "domain": "string",
    "api_key": "string"
  }
  ```
- **Returns (200):** A Bearer token for the device, the tenant's visual settings, and taxonomy definitions.
  ```json
  {
    "token": "1|abcdef...",
    "device": { "id": 1, "name": "Front Desk Tablet", "tenant_id": 1 },
    "tenant": { "id": 1, "name": "Demo School", "settings": { "primary_color": "#FFC0CB" } },
    "entity_types": [ { "id": 1, "name": "Class", "is_required": true } ]
  }
  ```

### `POST /api/auth/login`
- **Description:** Device login to retrieve a new token via API key.
- **Request Body:** `{ "api_key": "string" }`
- **Returns (200):** `{ "token": "...", "device": { "id": 1, "name": "Tablet", "tenant_id": 1 } }`

### `POST /api/admin/login`
- **Description:** Admin and SuperAdmin login endpoint for the React SPA.
- **Request Body:**
  ```json
  {
    "email": "user@example.com", // Omit this field entirely for default Platform/Super Admin login
    "password": "password123"
  }
  ```
- **Returns (200):** `{ "token": "...", "user": { "id": 1, "name": "...", "email": "...", "role": "admin|organisation", "tenant_id": 1 } }`

---

## 2. Authenticated Device Endpoints
*Requires: `Authorization: Bearer <device_token>`*

### `POST /api/auth/logout`
- **Description:** Revokes the device's current Bearer token.
- **Returns (200):** `{ "message": "Logged out." }`

### `GET /api/face/users`
- **Description:** Returns all users belonging to the device's tenant, specifically for the Face Enrollment dropdown UI.
- **Query Params:**
  - `filters` (optional array of arrays): Advanced AND/OR taxonomy filtering. E.g., `filters[0][]=1&filters[0][]=2` matches users with both Entity 1 AND Entity 2.
- **Returns (200):** Array of users.
  ```json
  [
    {
      "id": 1,
      "name": "John Doe",
      "member_uid": "MEM-001",
      "has_embedding": true,
      "entities": [{"type": "Class", "value": "10"}]
    }
  ]
  ```

### `GET /api/face/embeddings`
- **Description:** Incremental sync endpoint. Downloads face embeddings for the local on-device Hive cache.
- **Query Params:** 
  - `updated_after` (optional ISO8601): Fetch only incremental network changes.
  - `filters` (optional array of arrays): Advanced AND/OR taxonomy filtering. E.g., `filters[0][]=1` limits sync to a specific Class/Section.
- **Returns (200):** Array of objects.
  ```json
  [
    {
      "user_id": 1,
      "name": "Jane Doe",
      "embedding": "base64Encoded2048ByteFloat32List",
      "model_version": "w600k_mbf",
      "updated_at": "2024-10-10T14:30:00Z",
      "entities": [{"type": "Class", "value": "10"}]
    }
  ]
  ```

### `POST /api/face/enroll`
- **Description:** Registers a new face embedding for a specific user.
- **Request Body:**
  ```json
  {
    "user_id": 1,
    "embedding": "base64Encoded2048ByteFloat32List",
    "model_version": "w600k_mbf" // Optional
  }
  ```
- **Returns (201):** `{ "message": "Enrolled." }`

### `POST /api/face/match`
- **Description:** Server-side fallback for face matching (cosine similarity threshold mapping).
- **Request Body:** `{ "embedding": "base64Encoded2048ByteFloat32List" }`
- **Returns (200):** `{ "matched": true, "user_id": 1, "similarity": 0.540123 }`

### `POST /api/attendance/check-in` & `POST /api/attendance/check-out`
- **Description:** Fast single event trigger recording.
- **Request Body:** `{ "user_id": 1, "recorded_at": "2024-10-10T14:30:00Z" (optional), "similarity": 0.65 (optional) }`
- **Returns (201):** `{ ...LogObject }`

### `POST /api/attendance/sync`
- **Description:** Batch upload for offline attendance queues. Device pushes offline records.
- **Request Body:**
  ```json
  {
    "records": [
      { "local_id": "uuid", "user_id": 1, "type": "check_in", "recorded_at": "2024-10-10T14:30:00Z", "similarity": 0.55 }
    ]
  }
  ```
- **Returns (200):** 
  ```json
  {
    "synced": [{"local_id": "uuid", "server_id": 50}],
    "failed": [{"local_id": "uuid", "reason": "User not found."}]
  }
  ```

### `GET /api/attendance/sync`
- **Description:** Bidirectional continuous sync logic. Device downloads records captured by *other* terminals.
- **Query Params:** 
  - `since` (optional ISO8601): Fetch only recent logs.
  - `limit` (int, max 1000).
  - `filters` (optional array of arrays): Filter downloaded logs securely to only users matching specific taxonomies/groupings.
- **Returns (200):** Array of hydrated logs mapped down to `[{ id, user_id, type, recorded_at, device_id, similarity, user: { id, name, member_uid } }]`.

---

## 3. Authenticated Admin Endpoints (Organization Admin)
*Requires: `Authorization: Bearer <admin_token>`, `role = organisation`*

### `POST /api/admin/logout`
- **Description:** Revokes administrative token.

### `GET /api/admin/users`
- **Description:** Paginated list of users in the tenant.
- **Query Params:** 
  - `search` (string)
  - `entity_id` (int)
  - `entity_type_id` (int)
  - `filters` (optional array of arrays for advanced AND/OR grouping)
  - `per_page` (int)
- **Returns (200):** Paginated JSON, each User has `entities` and `has_face_enrolled`.

### `POST /api/admin/users`
- **Description:** Create a new user (employee/student).
- **Request Body:**
  ```json
  {
    "name": "string",
    "email": "string|email",
    "member_uid": "string", // optional
    "password": "password" // optional default string 
  }
  ```

### `GET /api/admin/users/{id}`
- **Description:** Inspect a single user with relations.
- **Returns (200):** Formatted user payload.

### `PUT /api/admin/users/{id}`
- **Description:** Modify a user object.
- **Request Body:** Any subset of the parameters accepted in `POST /api/admin/users`.

### `DELETE /api/admin/users/{id}`
- **Description:** Hard removes a user.
- **Returns (204):** Empty.

### `POST /api/admin/users/{id}/entities`
- **Description:** Sync taxonomy assignments (e.g., assigning User to "Class 10").
- **Request Body:** `{ "entity_ids": [1, 2, 5] }`

### `GET /api/admin/entity-types`
- **Description:** List structure definitions (e.g., "Class", "Section").
- **Query Params:** `with_entities` (boolean) - if `true`, eager-loads the full taxonomy tree, nesting the values inside their respective types.
- **Returns (200):** Array of types with an attached `entities_count`. If `with_entities=1` is passed, includes an `entities: [ {...} ]` nested array for each type.

### `POST /api/admin/entity-types`
- **Description:** Create a structure definition.
- **Request Body:** `{ "name": "string", "is_required": true|false }`

### `DELETE /api/admin/entity-types/{typeId}`
- **Description:** Delete a definition. Cascades down to all attached assignments.

### `GET /api/admin/entity-types/{typeId}/entities`
- **Description:** List options recursively inside a given type (e.g., "A", "B" inside "Section").
- **Returns (200):** Array of entities with attached `users_count`.

### `POST /api/admin/entity-types/{typeId}/entities`
- **Description:** Create a new option for a type.
- **Request Body:** `{ "name": "string" }`

### `DELETE /api/admin/entities/{id}`
- **Description:** Delete an entity option. Cascades down to un-assign the option from all users automatically.

### `GET /api/admin/devices`
- **Description:** List all currently provisioned devices/tablets for this specific organization tenant.
- **Returns (200):** Array of Devices.

### `POST /api/admin/devices`
- **Description:** Create a new local device entry, spawning a one-time API key.
- **Request Body:** `{ "name": "Main Entrance Tablet" }`
- **Returns (201):** The newly created device model. *Note: The `api_key` argument `"sk_xxxxxxxxxxxx"` acts as a one-time visible token in this initial JSON response.* 

### `DELETE /api/admin/devices/{id}`
- **Description:** Destroys and functionally bricks a device by immediately revoking its active Sanctum tokens and severing the DB entry.

### `GET /api/reports/attendance`
- **Description:** Generates offline reporting views.
- **Query Params:** `user_id`, `start_date`, `end_date`, `entity_id`, `entity_type_id`, `filters` (advanced taxonomy combinations), `format` (json | csv), `per_page`.
- **Returns:** Paginated JSON logs or a downloadable `.csv` file attachment mapping down user entities.

---

## 4. Super Admin Endpoints (Platform Admin)
*Requires: `Authorization: Bearer <admin_token>`, `role = admin`*

### `GET /api/super-admin/tenants`
- **Description:** Lists all SaaS organization Tenants.
- **Returns (200):** Array of Tenants mapped with a total `users_count`.

### `POST /api/super-admin/tenants`
- **Description:** Provisions a new independent SaaS tenant, configures their DB, creates their initial Org Admin, and dynamically auto-seeds taxonomies dependent on their `industry`.
- **Request Body:**
  ```json
  {
    "name": "GymCorp",
    "domain": "gymcorp.com",
    "industry": "fitness|education|corporate", // Modulates auto-seeded values 
    "admin_name": "Gym Admin",
    "admin_email": "admin@gymcorp.com",
    "admin_password": "securepassword"
  }
  ```
- **Returns (201):** `{ "message": "Organization created successfully", "tenant": {...} }`

### `GET /api/super-admin/tenants/{id}`
- **Description:** View a SaaS tenant with nested internal `entityTypes` configurations.

### `DELETE /api/super-admin/tenants/{id}`
- **Description:** Destroys a SaaS tenant and all associated data inside global scoped models.
