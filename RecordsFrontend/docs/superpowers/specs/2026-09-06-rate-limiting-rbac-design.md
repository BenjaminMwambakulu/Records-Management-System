# Rate Limiting & RBAC Enhancement — Design Spec

**Date:** 2026-09-06
**Status:** Draft
**Author:** opencode

## Overview

Add tiered rate limiting to the backend API and overhaul the RBAC system across both backend and frontend. The backend gains a new `/v1/me/permissions` endpoint, permission-based middleware on all routes, and a `dashboard.view` permission. The frontend fetches permissions on login and uses them for route guards, sidebar visibility, and page-level access control.

## Goals

- Add tiered rate limiting (global, read, write, sensitive) to all API routes
- Add `dashboard.view` permission — not every member should see the dashboard
- Expose user permissions via a backend endpoint for frontend consumption
- Replace role-based checks with permission-based checks on the frontend
- Harden backend routes with granular `permission:` middleware
- Create reusable frontend components for permission gating

## Non-Goals

- Logto-side permission configuration (permissions live in Spatie only)
- Rate limiting per-IP for authenticated users (keyed by user ID)
- Permission management UI (covered by existing Roles & Permissions spec)
- Audit logging of permission denials

---

## Section 1: Rate Limiting (Backend)

### Rate Limiters

Defined in `AppServiceProvider::boot()` using `RateLimiter::for()`:

| Limiter | Limit | Window | Key | Applies to |
|---|---|---|---|---|
| `api` | 60 | 1 min | `auth:id` or `ip` | All authenticated API routes (default) |
| `api-read` | 120 | 1 min | `auth:id` | GET-only routes (index, show) |
| `api-write` | 30 | 1 min | `auth:id` | POST, PUT, DELETE routes |
| `api-sensitive` | 10 | 1 min | `auth:id` or `ip` | Webhook, financial exports |
| `guest` | 30 | 1 min | `ip` | Unauthenticated routes |

### Implementation

In `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

public function boot(): void
{
    RateLimiterFacade::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiterFacade::for('api-read', function (Request $request) {
        return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiterFacade::for('api-write', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiterFacade::for('api-sensitive', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiterFacade::for('guest', function (Request $request) {
        return Limit::perMinute(30)->by($request->ip());
    });
}
```

### Response Format

When rate limit is exceeded:

```json
{
    "success": false,
    "message": "Too many requests. Please try again later.",
    "data": null,
    "errors": null
}
```

HTTP headers: `Retry-After: <seconds>`, `X-RateLimit-Remaining: 0`

### Route Application

In `routes/api.php`:

```php
// Public routes — guest throttle
Route::prefix('v1')->middleware('throttle:guest')->group(function () { ... });

// Authenticated routes — default api throttle
Route::prefix('v1')->middleware(['auth:logto', 'throttle:api'])->group(function () {
    // Read routes get api-read
    Route::get('dashboard/summary', [...])->middleware('throttle:api-read');
    Route::get('activity-logs', [...])->middleware('throttle:api-read');
    // ... other GET routes

    // Write routes get api-write
    Route::middleware(['throttle:api-write'])->group(function () {
        Route::apiResource('members', MemberController::class);
        Route::post('events', [...]);
        Route::put('events/{event}', [...]);
        Route::delete('events/{event}', [...]);
        // ... other write routes
    });

    // Sensitive routes
    Route::get('financial-records/export', [...])->middleware('throttle:api-sensitive');
});
```

---

## Section 2: Backend RBAC Enhancement

### New Permission

Add `dashboard.view` to `RolesAndPermissionsSeeder`:

```php
'dashboard' => ['dashboard.view'],
```

Role assignments:
- `superadmin` → all permissions (including `dashboard.view`)
- `executive` → gains `dashboard.view`
- `member` → no `dashboard.view`
- `alumni` → no `dashboard.view`

### New Endpoint: `GET /v1/me/permissions`

**Controller:** `MeController::permissions()`

Returns the authenticated user's Spatie permissions as a flat array of permission names.

**Response:**
```json
{
    "success": true,
    "message": "Permissions retrieved successfully",
    "data": {
        "permissions": [
            "members.view",
            "members.create",
            "events.view",
            "dashboard.view",
            "roles.manage"
        ]
    }
}
```

**Route:** Inside `auth:logto` group, no additional middleware (any authenticated user can fetch their own permissions).

### Route Hardening

Add `permission:` middleware to routes that currently only use `deny_member`:

| Route | Current Middleware | New Middleware |
|---|---|---|
| `GET /v1/dashboard/summary` | `deny_member` | `deny_member`, `permission:dashboard.view,logto` |
| `GET /v1/activity-logs` | `deny_member` | `deny_member`, `permission:activity_logs.view,logto` |
| `GET /v1/members` | `deny_member` | `deny_member`, `permission:members.view,logto` |
| `POST /v1/members` | `deny_member` | `deny_member`, `permission:members.create,logto` |
| `PUT /v1/members/{member}` | `deny_member` | `deny_member`, `permission:members.update,logto` |
| `DELETE /v1/members/{member}` | `deny_member` | `deny_member`, `permission:members.delete,logto` |
| `POST /v1/events` | `deny_member` | `deny_member`, `permission:events.create,logto` |
| `PUT /v1/events/{event}` | `deny_member` | `deny_member`, `permission:events.update,logto` |
| `DELETE /v1/events/{event}` | `deny_member` | `deny_member`, `permission:events.delete,logto` |
| `POST /v1/documents` | `deny_member` | `deny_member`, `permission:documents.create,logto` |
| `PUT /v1/documents/{document}` | `deny_member` | `deny_member`, `permission:documents.update,logto` |
| `DELETE /v1/documents/{document}` | `deny_member` | `deny_member`, `permission:documents.delete,logto` |
| `GET /v1/assets` | `deny_member` | `deny_member`, `permission:assets.view,logto` |
| `POST /v1/assets` | `deny_member` | `deny_member`, `permission:assets.create,logto` |
| `PUT /v1/assets/{asset}` | `deny_member` | `deny_member`, `permission:assets.update,logto` |
| `DELETE /v1/assets/{asset}` | `deny_member` | `deny_member`, `permission:assets.delete,logto` |

Note: `deny_member` remains as the first check (fast rejection of member role). `permission:` middleware runs second for granular check.

---

## Section 3: Frontend RBAC

### New Files

#### 1. `src/lib/permissions.js`

```js
export function hasPermission(user, permission) {
  if (!user || !Array.isArray(user.permissions)) return false;
  return user.permissions.includes(permission);
}

export function hasAnyPermission(user, permissions) {
  return permissions.some((p) => hasPermission(user, p));
}

export function hasAllPermissions(user, permissions) {
  return permissions.every((p) => hasPermission(user, p));
}
```

#### 2. `src/hooks/useAuthorization.js`

- On login, fetches `GET /v1/me/permissions`
- Stores `permissions` array in AuthContext
- Exposes `hasPermission`, `hasAnyPermission`, `hasAllPermissions` helpers
- Returns `{ permissions, hasPermission, hasAnyPermission, hasAllPermissions, isLoading }`

#### 3. `src/components/PermissionGate.jsx`

```jsx
function PermissionGate({ permission, permissions, requireAll = false, fallback, children }) {
  const { user } = useAuth();
  const check = requireAll ? hasAllPermissions : hasAnyPermission;
  const perms = permission ? [permission] : permissions;

  if (!check(user, perms)) {
    return fallback || <AccessDenied />;
  }
  return children;
}
```

#### 4. `src/components/AccessDenied.jsx`

Shared component with configurable `message` and `icon` props.

### Modified Files

#### `src/lib/roles.js`

```js
import { hasPermission } from './permissions';

export function canAccessDashboard(user) {
  return hasPermission(user, 'dashboard.view');
}

export function isEventManager(user) {
  return hasPermission(user, 'events.view');
}

export function isDocumentManager(user) {
  return hasPermission(user, 'documents.view');
}

export function isFinancialManager(user) {
  return hasPermission(user, 'financials.view');
}

export function isAssetManager(user) {
  return hasPermission(user, 'assets.view');
}
```

#### `src/Context/AuthContext.jsx`

- Add `permissions` and `setPermissions` to state
- On login, after getting user info, fetch `/v1/me/permissions` and store in context
- Expose `permissions` in the context value

#### `src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx`

Replace `adminOnly` with `permission` field on nav items:

```js
const navData = [
  { title: "Dashboard", icon: LayoutDashboard, href: "/app", permission: "dashboard.view" },
  { title: "Members", icon: Users, href: "/app/members", permission: "members.view" },
  { title: "Events", icon: CalendarDays, href: "/app/events", permission: "events.view" },
  { title: "Financial Records", icon: Wallet, href: "/app/financial-records", permission: "financials.view" },
  { title: "Assets", icon: Package, href: "/app/assets", permission: "assets.view" },
  { title: "Documents", icon: FolderOpen, href: "/app/documents", permission: "documents.view" },
  { title: "Activity Logs", icon: History, href: "/app/logs", permission: "activity_logs.view" },
  { title: "Roles & Permissions", icon: Shield, href: "/app/roles", permission: "roles.manage" },
];
```

`visibleNavData` filters using `hasPermission(user, item.permission)`.

#### `src/App.jsx`

- `ProtectedRoute` continues to use `canAccessDashboard(user)` (now permission-based)
- No structural changes needed

#### Page components

Each page wraps its content with `<PermissionGate>`:

- `Dashboard.jsx` → `<PermissionGate permission="dashboard.view">`
- `MembersIndex.jsx` → `<PermissionGate permission="members.view">`
- `EventsIndex.jsx` → `<PermissionGate permission="events.view">`
- `FinancialRecordsIndex.jsx` → `<PermissionGate permission="financials.view">`
- `AssetsIndex.jsx` → `<PermissionGate permission="assets.view">`
- `DocumentsIndex.jsx` → `<PermissionGate permission="documents.view">`
- `LogsIndex.jsx` → `<PermissionGate permission="activity_logs.view">`
- `RolesIndex.jsx` → `<PermissionGate permission="roles.manage">`
- `RoleDetailPage.jsx` → `<PermissionGate permission="roles.manage">`

---

## Error Handling

- **Rate limiting:** 422 response with `Retry-After` header
- **Permission denied (backend):** 403 via Spatie middleware → rendered as JSON by exception handler
- **Permission denied (frontend):** `<AccessDenied />` component with message

## Testing

### Backend
- Rate limiter tests: verify limits are enforced per tier
- Permission endpoint test: verify correct permissions returned for each role
- Route middleware tests: verify 403 for unauthorized access
- Seeder test: verify `dashboard.view` permission exists and is assigned correctly

### Frontend
- `npm run lint` — no new warnings
- `npm run build` — succeeds
- Manual verification: login as member → dashboard hidden; login as executive → dashboard visible

## Migration Path

- One new database permission: `dashboard.view` (added via seeder, no migration needed)
- No schema changes
- Backward-compatible: existing tokens continue to work; new permission is additive
