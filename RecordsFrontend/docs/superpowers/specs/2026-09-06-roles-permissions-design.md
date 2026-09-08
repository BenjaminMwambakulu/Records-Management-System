# Roles & Permissions Management — Design Spec

**Date:** 2026-09-06
**Status:** Draft
**Author:** opencode

## Overview

Admin-only page for managing roles, assigning permissions to roles, and assigning roles to users. Provides a CRUD interface for the Spatie Permission system that currently can only be managed via seeder or member assignment flows.

## Goals

- Allow admins to create, edit, and delete custom roles
- Allow admins to assign existing permissions to roles via a checkbox matrix
- Allow admins to assign roles to users from the roles detail page
- Enforce access control: only admin/superadmin can access
- Prevent destructive actions on the superadmin role

## Non-Goals

- Creating/deleting permissions (permissions are system-defined, fixed by the seeder)
- Bulk role assignment across multiple users
- Role hierarchy/inheritance management
- Logto role management (local Spatie only — Logto sync is handled by existing RoleSyncService)

## Existing Infrastructure

- **Package:** Spatie Laravel Permission v8.3
- **Guard:** `logto` (custom guard, not `web`)
- **Roles (from seeder):** `superadmin`, `executive`, `member`, `alumni`
- **Permissions (from seeder):** ~20 permissions across modules: `members.*`, `events.*`, `financials.*`, `assets.*`, `documents.*`, `system.*`
- **User model:** Uses `HasRoles` trait from Spatie
- **Frontend auth:** Logto JWT — roles come from access token claims
- **Frontend role checks:** `isAdminUser(user)` checks for `super_admin` or `org_admin` Logto role names

## Backend Design

### New Controller: `RoleController`

File: `app/Http/Controllers/Api/RoleController.php`

All methods behind `role:admin|superadmin,logto` middleware.

#### Endpoints

| Route | Method | Controller Method | Purpose |
|---|---|---|---|
| `GET /v1/roles` | GET | `index` | List all roles with counts |
| `POST /v1/roles` | POST | `store` | Create a new role |
| `GET /v1/roles/{role}` | GET | `show` | Role detail: permissions + users |
| `PUT /v1/roles/{role}` | PUT | `update` | Update role name/description |
| `DELETE /v1/roles/{role}` | DELETE | `destroy` | Delete role (block superadmin) |
| `GET /v1/permissions` | GET | `permissions` | List all permissions grouped by module |
| `PUT /v1/roles/{role}/permissions` | PUT | `syncPermissions` | Replace all permissions for a role |
| `POST /v1/roles/{role}/users` | POST | `addUser` | Assign role to a user |
| `DELETE /v1/roles/{role}/users/{user}` | DELETE | `removeUser` | Remove role from a user |

#### Route Registration

In `routes/api.php`, inside the `auth:logto` group:

```php
Route::apiResource('roles', RoleController::class);
Route::get('permissions', [RoleController::class, 'permissions']);
Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
Route::post('roles/{role}/users', [RoleController::class, 'addUser']);
Route::delete('roles/{role}/users/{user}', [RoleController::class, 'removeUser']);
```

#### Validation Rules

- `store`: `name` required, string, max:255, unique:roles,name (guard: logto)
- `update`: `name` required, string, max:255, unique:roles,name,{id} (guard: logto)
- `syncPermissions`: `permissions` required, array, each string exists:permissions,name (guard: logto)
- `addUser`: `user_id` required, integer, exists:users,id
- Cannot delete `superadmin` role → return 403
- Cannot remove `superadmin` role from the last superadmin user → return 422

#### Response Shapes

**GET /v1/roles:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "executive",
      "guard_name": "logto",
      "permissions_count": 15,
      "users_count": 3,
      "created_at": "2026-09-01T00:00:00.000000Z"
    }
  ]
}
```

**GET /v1/roles/{role}:**
```json
{
  "data": {
    "id": 1,
    "name": "executive",
    "guard_name": "logto",
    "permissions": [
      { "id": 1, "name": "members.view" },
      { "id": 2, "name": "members.create" }
    ],
    "users": [
      { "id": 5, "first_name": "Jane", "last_name": "Smith", "email": "jane@must.ac.mw" }
    ],
    "created_at": "2026-09-01T00:00:00.000000Z"
  }
}
```

**GET /v1/permissions:**
```json
{
  "data": {
    "members": ["members.view", "members.create", "members.update", "members.delete", "members.export"],
    "events": ["events.view", "events.create", "events.update", "events.delete", "events.checkin"],
    "financials": ["financials.view", "financials.create", "financials.update", "financials.delete", "financials.export"],
    "assets": ["assets.view", "assets.create", "assets.update", "assets.delete", "assets.checkout", "assets.return"],
    "documents": ["documents.view", "documents.create", "documents.update", "documents.delete", "documents.versions.create"],
    "system": ["roles.manage", "activity_logs.view"]
  }
}
```

### No New Models

Uses Spatie's existing `Spatie\Permission\Models\Role` and `Spatie\Permission\Models\Permission` directly. No custom models needed.

## Frontend Design

### Route Structure

In `App.jsx`:

```jsx
<Route path="roles" element={<RolesIndex />} />
<Route path="roles/:id" element={<RoleDetailPage />} />
```

### Sidebar Navigation

In `app-sidebar.jsx`, add to the Admin section:

```jsx
{ title: "Roles & Permissions", icon: Shield, href: "/roles", adminOnly: true }
```

### Pages

#### 1. `RolesIndex.jsx` — Roles List

File: `src/Pages/AdminDirectory/RolesIndex.jsx`

- Header: "Roles & Permissions" + "Create role" button (admin only)
- Table columns: Name, Permissions (count badge), Users (count badge), Created, Actions (Edit/Delete)
- Click row → navigate to `/roles/:id`
- Edit button → opens edit dialog (name only)
- Delete button → opens delete confirmation (block if superadmin)
- Empty state when no roles
- Error/retry state

#### 2. `RoleDetailPage.jsx` — Role Detail

File: `src/Pages/AdminDirectory/RoleDetailPage.jsx`

- Header: role name + edit/delete buttons
- **Permissions section:**
  - Checkbox matrix grouped by module (members, events, financials, assets, documents, system)
  - Each module is a collapsible section with a header + checkbox count
  - "Select all" per module
  - "Save permissions" button at bottom
  - Loading/saving states
- **Assigned users section:**
  - Table of users with this role: Name, Email, Actions (Remove)
  - "Add user" button → opens a user search/select dialog
  - Empty state when no users assigned

#### 3. `RoleFormDialog.jsx` — Create/Edit Role

File: `src/Pages/AdminDirectory/RoleFormDialog.jsx`

- Dialog with name input (required)
- Create mode: empty form
- Edit mode: pre-filled with current role name
- Submit button + Cancel

#### 4. `DeleteRoleDialog.jsx` — Delete Confirmation

File: `src/Pages/AdminDirectory/DeleteRoleDialog.jsx`

- Standard confirmation dialog
- Shows role name
- Warning if role has assigned users
- Cannot confirm if superadmin (button disabled + tooltip)

#### 5. `AssignUserDialog.jsx` — Assign Role to User

File: `src/Pages/AdminDirectory/AssignUserDialog.jsx`

- Dialog with user search input (debounced)
- Lists matching users not already assigned to this role
- Click to assign
- Shows loading/empty states

### New Hook: `useRolesDirectory.js`

File: `src/hooks/useRolesDirectory.js`

```js
// State
roles, isLoading, error
currentRole, currentRoleLoading, currentRoleError
permissions, permissionsLoading
isSubmitting

// Actions
fetchRoles()
fetchRole(id)
fetchPermissions()
createRole(data)
updateRole(id, data)
deleteRole(id)
syncPermissions(roleId, permissionIds)
assignRoleToUser(roleId, userId)
removeRoleFromUser(roleId, userId)
```

All mutations call the API and re-fetch affected data on success.

### Access Control (Frontend)

- Both pages check `isAdminUser(user)` and show `<AccessDenied />` if not admin
- Sidebar `adminOnly: true` hides the nav link for non-admins
- Same pattern as existing Activity Logs page

## Error Handling

- **Backend:** Standard `APIResponse::error()` for all error cases (403, 404, 422, 500)
- **Frontend:** `extractErrorMessage()` for API errors, displayed in error boxes (matching existing pattern)
- **Optimistic updates:** None — all mutations re-fetch data after success

## Testing

### Backend

- Feature tests for each endpoint (CRUD, permissions sync, user assignment)
- Test superadmin role deletion is blocked
- Test last superadmin role removal is blocked
- Test validation rules
- Run `vendor/bin/pest` — all green
- Run `vendor/bin/pint --dirty` — clean

### Frontend

- `npm run lint` — no new warnings
- `npm run build` — succeeds
- No JS test runner (manual verification only)

## Migration Path

- No database migrations needed — uses existing Spatie tables
- Seeder already defines initial roles and permissions
- No data changes required

## Open Questions

- None at this time.
