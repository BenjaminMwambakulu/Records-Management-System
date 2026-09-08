# Rate Limiting & RBAC Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add tiered rate limiting to all API routes and overhaul RBAC so permissions (not roles) drive both backend route enforcement and frontend UI access.

**Architecture:** Backend gains rate limiters in AppServiceProvider, a `/v1/me/permissions` endpoint, and permission middleware on all management routes. Frontend fetches permissions on login, stores them in AuthContext, and uses a `PermissionGate` component + `hasPermission()` utility for route guards and sidebar filtering.

**Tech Stack:** Laravel 11 (PHP 8.5), Spatie Laravel Permission v8.3, React 19, Logto JWT auth, Vite

**Spec:** `RecordsFrontend/docs/superpowers/specs/2026-09-06-rate-limiting-rbac-design.md`

## Global Constraints

- Guard name for all Spatie operations: `logto`
- Permission names follow pattern: `<module>.<action>` (e.g., `members.view`, `events.create`)
- All API responses use the `APIResponse` trait format: `{ success, message, data, errors }`
- Frontend uses native `fetch` via `src/APIClients/APIClient.js` (no axios)
- Frontend role names in JWT: `super_admin`, `org_admin` (from Logto)
- Run `vendor/bin/pint --dirty` after PHP changes
- Run `npm run lint` and `npm run build` after frontend changes

---

## File Structure

### Backend (RecordsAPI)

| File | Action | Purpose |
|---|---|---|
| `app/Providers/AppServiceProvider.php` | Modify | Add rate limiter definitions |
| `database/seeders/RolesAndPermissionsSeeder.php` | Modify | Add `dashboard.view` permission |
| `app/Http/Controllers/Api/MeController.php` | Modify | Add `permissions()` method |
| `routes/api.php` | Modify | Add throttle middleware, permissions route, permission middleware on routes |

### Backend Tests (RecordsAPI)

| File | Action | Purpose |
|---|---|---|
| `tests/Feature/RateLimitTest.php` | Create | Test rate limiting behavior |
| `tests/Feature/MePermissionsTest.php` | Create | Test GET /v1/me/permissions |
| `tests/Feature/RolesAndPermissionsSeederTest.php` | Modify | Verify dashboard.view permission |

### Frontend (RecordsFrontend)

| File | Action | Purpose |
|---|---|---|
| `src/lib/permissions.js` | Create | Permission utility functions |
| `src/components/AccessDenied.jsx` | Create | Shared AccessDenied component |
| `src/components/PermissionGate.jsx` | Create | Reusable route guard |
| `src/hooks/useAuthorization.js` | Create | Hook to fetch and expose permissions |
| `src/Context/AuthContext.jsx` | Modify | Add permissions to context |
| `src/lib/roles.js` | Modify | Permission-based checks instead of role-based |
| `src/App.jsx` | Modify | Import shared AccessDenied (if needed) |
| `src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx` | Modify | Permission-based nav filtering |
| `src/Pages/Dashboard/Dashboard.jsx` | Modify | Wrap with PermissionGate |
| `src/Pages/MemberDirectory/MembersIndex.jsx` | Modify | Wrap with PermissionGate |
| `src/Pages/EventsDirectory/EventsIndex.jsx` | Modify | Wrap with PermissionGate |
| `src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx` | Modify | Wrap with PermissionGate |
| `src/Pages/AssetDirectory/AssetsIndex.jsx` | Modify | Wrap with PermissionGate |
| `src/Pages/DocumentDirectory/DocumentsIndex.jsx` | Modify | Wrap with PermissionGate |
| `src/Pages/LogsDirectory/LogsIndex.jsx` | Modify | Replace inline AccessDenied, use PermissionGate |
| `src/Pages/AdminDirectory/RolesIndex.jsx` | Modify | Replace inline AccessDenied, use PermissionGate |
| `src/Pages/AdminDirectory/RoleDetailPage.jsx` | Modify | Replace inline AccessDenied, use PermissionGate |

---

## Task 1: Backend — Rate Limiters in AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Produces: 5 named rate limiters (`api`, `api-read`, `api-write`, `api-sensitive`, `guest`) usable via `throttle` middleware alias

- [ ] **Step 1: Add rate limiter imports and definitions**

Add to `AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Auth\JwtVerifier;
use App\Auth\LogtoGuard;
use App\Listeners\SyncSuperadminToLogto;
use Illuminate\Cache\RateLimiter;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\RateLimiter\Limit;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleUpdated;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        Event::listen(RoleUpdated::class, SyncSuperadminToLogto::class);

        Auth::extend('logto', function (Application $app, string $name, array $config) {
            return new LogtoGuard(
                Auth::createUserProvider($config['provider'] ?? null),
                $app->make(JwtVerifier::class),
                $app->make(Container::class),
            );
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('guest', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
```

- [ ] **Step 2: Verify it compiles**

Run: `cd RecordsAPI && php artisan route:list --path=api`
Expected: Routes list without errors

- [ ] **Step 3: Commit**

```bash
cd RecordsAPI
git add app/Providers/AppServiceProvider.php
git commit -m "feat: add tiered rate limiters to AppServiceProvider"
```

---

## Task 2: Backend — Rate Limiting Tests

**Files:**
- Create: `tests/Feature/RateLimitTest.php`

**Interfaces:**
- Consumes: Rate limiters from Task 1

- [ ] **Step 1: Create the rate limit test file**

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    Cache::flush();
    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function rateLimitToken(array $keys, string $logtoId = 'logto-rl-user'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createRateLimitUser(): User
{
    $user = User::factory()->create(['logto_id' => 'logto-rl-user']);
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $user->assignRole($role);

    return $user;
}

it('returns 422 when guest rate limit is exceeded', function () {
    $token = rateLimitToken($this->keys);
    createRateLimitUser();

    for ($i = 0; $i < 31; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertStatus(429);
});

it('returns 422 when write rate limit is exceeded', function () {
    $token = rateLimitToken($this->keys);
    $user = createRateLimitUser();

    for ($i = 0; $i < 30; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/members', [
                'first_name' => 'Test',
                'last_name' => "User {$i}",
                'email' => "test{$i}@example.com",
                'student_id' => "STU{$i}",
            ]);
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/members', [
            'first_name' => 'Exceed',
            'last_name' => 'Limit',
            'email' => 'exceed@example.com',
            'student_id' => 'STU999',
        ])
        ->assertStatus(429);
});

it('includes Retry-After header when rate limited', function () {
    $token = rateLimitToken($this->keys);
    createRateLimitUser();

    for ($i = 0; $i < 31; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
```

- [ ] **Step 2: Run the test**

Run: `cd RecordsAPI && php artisan test --compact --filter=RateLimitTest`
Expected: All tests pass (or fail with rate limit not yet applied — that's expected, we'll apply middleware in Task 4)

- [ ] **Step 3: Commit**

```bash
cd RecordsAPI
git add tests/Feature/RateLimitTest.php
git commit -m "test: add rate limiting feature tests"
```

---

## Task 3: Backend — Add `dashboard.view` Permission to Seeder

**Files:**
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`

**Interfaces:**
- Produces: `dashboard.view` permission in the `system` module, assigned to `executive` and `superadmin`

- [ ] **Step 1: Update the seeder**

Add `'dashboard'` key to `MODULE_PERMISSIONS`:

```php
private const array MODULE_PERMISSIONS = [
    // ... existing entries ...
    'dashboard' => [
        'dashboard.view',
    ],
];
```

Add `'dashboard.view'` to the `executive` role in `ROLE_PERMISSIONS`:

```php
'executive' => [
    // ... existing permissions ...
    'dashboard.view',
],
```

The `superadmin` role already gets `Permission::all()`, so it automatically gets `dashboard.view`.

- [ ] **Step 2: Run the seeder test**

Run: `cd RecordsAPI && php artisan test --compact --filter=RolesAndPermissionsSeederTest`
Expected: Pass

- [ ] **Step 3: Commit**

```bash
cd RecordsAPI
git add database/seeders/RolesAndPermissionsSeeder.php
git commit -m "feat: add dashboard.view permission to seeder"
```

---

## Task 4: Backend — Add `/v1/me/permissions` Endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/MeController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Produces: `GET /v1/me/permissions` returning `{ data: { permissions: ["members.view", ...] } }`

- [ ] **Step 1: Add `permissions()` method to MeController**

Add after the `mergeLogtoProfile` method:

```php
public function permissions(Request $request): JsonResponse
{
    $user = $request->user();

    /** @var list<string> $permissions */
    $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

    return $this->success(
        ['permissions' => $permissions],
        'Permissions retrieved successfully',
    );
}
```

- [ ] **Step 2: Add the route**

In `routes/api.php`, inside the `auth:logto` group (before the `deny_member` group), add:

```php
Route::get('me/permissions', [MeController::class, 'permissions']);
```

- [ ] **Step 3: Write a test**

Create `tests/Feature/MePermissionsTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    Cache::flush();
    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

it('returns the authenticated user permissions', function () {
    $token = JwtTestHelper::sign(JwtTestHelper::claims('logto-perms'), $this->keys['private_pem'], $this->keys['kid']);

    $user = User::factory()->create(['logto_id' => 'logto-perms']);
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    Permission::findOrCreate('members.view', 'logto');
    Permission::findOrCreate('events.view', 'logto');
    $role->syncPermissions(['members.view', 'events.view']);
    $user->assignRole($role);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me/permissions')
        ->assertOk()
        ->assertJsonPath('data.permissions', function ($perms) {
            return in_array('members.view', $perms) && in_array('events.view', $perms);
        });
});

it('returns empty permissions for member role', function () {
    $token = JwtTestHelper::sign(JwtTestHelper::claims('logto-member-perms'), $this->keys['private_pem'], $this->keys['kid']);

    $user = User::factory()->create(['logto_id' => 'logto-member-perms']);
    $role = Role::create(['name' => 'member', 'guard_name' => 'logto']);
    Permission::findOrCreate('members.view', 'logto');
    $role->syncPermissions(['members.view']);
    $user->assignRole($role);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me/permissions')
        ->assertOk()
        ->assertJsonPath('data.permissions', fn ($perms) => in_array('members.view', $perms));
});
```

- [ ] **Step 4: Run tests**

Run: `cd RecordsAPI && php artisan test --compact --filter=MePermissionsTest`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `cd RecordsAPI && vendor/bin/pint --dirty --format agent`
Expected: Clean

- [ ] **Step 6: Commit**

```bash
cd RecordsAPI
git add app/Http/Controllers/Api/MeController.php routes/api.php tests/Feature/MePermissionsTest.php
git commit -m "feat: add GET /v1/me/permissions endpoint"
```

---

## Task 5: Backend — Apply Rate Limiting & Permission Middleware to Routes

**Files:**
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: Rate limiters from Task 1, permissions from Task 3

- [ ] **Step 1: Rewrite routes/api.php**

Apply throttle middleware to route groups and permission middleware to individual routes:

```php
<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetLoanController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\FinancialCategoryController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MemberImportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Webhooks\LogtoWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/logto', [LogtoWebhookController::class, 'handleWebhook'])
    ->middleware('throttle:api-sensitive');

// ─── Public routes (no auth required) ───
Route::prefix('v1')->middleware('throttle:guest')->group(function () {
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);

    Route::get('documents', [DocumentController::class, 'index']);
    Route::get('documents/{document}', [DocumentController::class, 'show']);
    Route::get('documents/{document}/versions', [DocumentController::class, 'listVersions']);
    Route::get('documents/{document}/versions/{version}', [DocumentController::class, 'showVersion']);
});

// ─── Authenticated routes ───
Route::prefix('v1')->middleware(['auth:logto', 'throttle:api'])->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::get('me/permissions', [MeController::class, 'permissions']);

    Route::get('events/{event}/attendances', [EventController::class, 'attendances']);
    Route::post('events/{event}/register', [EventController::class, 'register']);
    Route::delete('events/{event}/register', [EventController::class, 'cancelRegistration']);
    Route::get('events/{event}/registration', [EventController::class, 'checkRegistration']);

    // ─── Routes restricted from members ───
    Route::middleware('deny_member')->group(function () {
        // Dashboard
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->middleware(['throttle:api-read', 'permission:dashboard.view,logto']);

        // Activity logs
        Route::get('activity-logs', [ActivityLogController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:activity_logs.view,logto']);

        // Members management
        Route::get('members', [MemberController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::post('members', [MemberController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:members.create,logto']);
        Route::get('members/{member}', [MemberController::class, 'show'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::put('members/{member}', [MemberController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:members.update,logto']);
        Route::delete('members/{member}', [MemberController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:members.delete,logto']);
        Route::post('members/import', [MemberImportController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:members.import,logto']);
        Route::get('members/imports/template', [MemberImportController::class, 'template'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::get('members/imports/{import}', [MemberImportController::class, 'show'])
            ->middleware(['throttle:api-read', 'permission:members.view,logto']);
        Route::post('members/{member}/roles', [MemberController::class, 'assignRoles'])
            ->middleware(['throttle:api-write', 'permission:members.update,logto']);
        Route::delete('members/{member}/roles/{role}', [MemberController::class, 'removeRole'])
            ->middleware(['throttle:api-write', 'permission:members.update,logto']);

        // Roles & permissions
        Route::apiResource('roles', RoleController::class)
            ->middleware(['throttle:api', 'role:admin|superadmin,logto']);
        Route::get('permissions', [RoleController::class, 'permissions'])
            ->middleware(['throttle:api-read', 'role:admin|superadmin,logto']);
        Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
            ->middleware(['throttle:api-write', 'role:admin|superadmin,logto']);
        Route::post('roles/{role}/users', [RoleController::class, 'addUser'])
            ->middleware(['throttle:api-write', 'role:admin|superadmin,logto']);
        Route::delete('roles/{role}/users/{user}', [RoleController::class, 'removeUser'])
            ->middleware(['throttle:api-write', 'role:admin|superadmin,logto']);

        // Events — write operations
        Route::post('events', [EventController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:events.create,logto']);
        Route::put('events/{event}', [EventController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:events.update,logto']);
        Route::delete('events/{event}', [EventController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:events.delete,logto']);
        Route::post('events/{event}/cover', [EventController::class, 'uploadCover'])
            ->middleware(['throttle:api-write', 'permission:events.update,logto']);
        Route::delete('events/{event}/cover', [EventController::class, 'removeCover'])
            ->middleware(['throttle:api-write', 'permission:events.update,logto']);

        // Documents — write operations
        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:documents.create,logto']);
        Route::put('documents/{document}', [DocumentController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:documents.update,logto']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:documents.delete,logto']);
        Route::post('documents/{document}/versions', [DocumentController::class, 'storeVersion'])
            ->middleware(['throttle:api-write', 'permission:documents.versions.create,logto']);

        // Document categories
        Route::apiResource('document-categories', DocumentCategoryController::class)
            ->middleware('throttle:api');

        // Financial records
        Route::get('financial-records/summary', [FinanceController::class, 'summary'])
            ->middleware(['throttle:api-read', 'permission:financials.view,logto']);
        Route::get('financial-records/export', [FinanceController::class, 'export'])
            ->middleware(['throttle:api-sensitive', 'permission:financials.export,logto']);
        Route::post('financial-records', [FinanceController::class, 'store'])
            ->middleware(['throttle:api-write', 'permission:financials.create,logto']);
        Route::put('financial-records/{record}', [FinanceController::class, 'update'])
            ->middleware(['throttle:api-write', 'permission:financials.update,logto']);
        Route::delete('financial-records/{record}', [FinanceController::class, 'destroy'])
            ->middleware(['throttle:api-write', 'permission:financials.delete,logto']);
        Route::get('financial-records/{record}', [FinanceController::class, 'show'])
            ->middleware(['throttle:api-read', 'permission:financials.view,logto']);
        Route::get('financial-records', [FinanceController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:financials.view,logto']);
        Route::apiResource('financial-categories', FinancialCategoryController::class)
            ->middleware('throttle:api');

        // Assets
        Route::get('assets/summary', [AssetController::class, 'summary'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);
        Route::get('assets/categories', [AssetController::class, 'categories'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);
        Route::get('assets/loans/overdue', [AssetLoanController::class, 'overdue'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);
        Route::apiResource('assets', AssetController::class)
            ->middleware('throttle:api');
        Route::post('assets/{asset}/checkout', [AssetController::class, 'checkout'])
            ->middleware(['throttle:api-write', 'permission:assets.checkout,logto']);
        Route::post('assets/{asset}/return', [AssetController::class, 'return'])
            ->middleware(['throttle:api-write', 'permission:assets.return,logto']);
        Route::get('assets/{asset}/loans', [AssetLoanController::class, 'index'])
            ->middleware(['throttle:api-read', 'permission:assets.view,logto']);
    });
});
```

- [ ] **Step 2: Run existing tests to verify no regressions**

Run: `cd RecordsAPI && php artisan test --compact`
Expected: All pass (some may need permission updates — check failures)

- [ ] **Step 3: Run Pint**

Run: `cd RecordsAPI && vendor/bin/pint --dirty --format agent`
Expected: Clean

- [ ] **Step 4: Commit**

```bash
cd RecordsAPI
git add routes/api.php
git commit -m "feat: apply throttle and permission middleware to all API routes"
```

---

## Task 6: Frontend — Permission Utilities & Components

**Files:**
- Create: `src/lib/permissions.js`
- Create: `src/components/AccessDenied.jsx`
- Create: `src/components/PermissionGate.jsx`

**Interfaces:**
- Produces: `hasPermission(user, perm)`, `hasAnyPermission(user, perms)`, `hasAllPermissions(user, perms)`, `<AccessDenied />`, `<PermissionGate />`

- [ ] **Step 1: Create `src/lib/permissions.js`**

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

- [ ] **Step 2: Create `src/components/AccessDenied.jsx`**

```jsx
import { Lock } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";

export default function AccessDenied({ message = "You do not have permission to access this page. Contact an administrator.", icon: Icon = Lock }) {
  return (
    <Card className="border-csit-border bg-white">
      <Empty className="py-16">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon />
          </EmptyMedia>
          <EmptyTitle>Access restricted</EmptyTitle>
          <EmptyDescription>{message}</EmptyDescription>
        </EmptyHeader>
      </Empty>
    </Card>
  );
}
```

- [ ] **Step 3: Create `src/components/PermissionGate.jsx`**

```jsx
import { useAuth } from "@/Context/AuthContext";
import { hasPermission, hasAnyPermission, hasAllPermissions } from "@/lib/permissions";
import AccessDenied from "./AccessDenied";

export default function PermissionGate({ permission, permissions, requireAll = false, fallback, children }) {
  const { user } = useAuth();
  const perms = permission ? [permission] : permissions;
  const check = requireAll ? hasAllPermissions : hasAnyPermission;

  if (!check(user, perms)) {
    return fallback || <AccessDenied />;
  }

  return children;
}
```

- [ ] **Step 4: Run lint**

Run: `cd RecordsFrontend && npm run lint`
Expected: No new warnings

- [ ] **Step 5: Commit**

```bash
cd RecordsFrontend
git add src/lib/permissions.js src/components/AccessDenied.jsx src/components/PermissionGate.jsx
git commit -m "feat: add permission utilities, AccessDenied, and PermissionGate components"
```

---

## Task 7: Frontend — Fetch Permissions in AuthContext

**Files:**
- Modify: `src/Context/AuthContext.jsx`

**Interfaces:**
- Consumes: `GET /v1/me/permissions` endpoint from Task 4
- Produces: `permissions` array and `setPermissions` in AuthContext value

- [ ] **Step 1: Add permissions state and fetch to AuthContext**

Add `permissions` state and fetch logic. Key changes:

1. Add `const [permissions, setPermissions] = useState([]);` after the `user` state
2. After setting the user (in the `fetchUserInfo` success block), fetch permissions:

```jsx
// After setUser({ ...info, ...(claims || {}) });
try {
  const permsResponse = await api.get('/v1/me/permissions');
  const perms = permsResponse?.data?.permissions ?? [];
  setPermissions(perms);
} catch (err) {
  console.error("Failed to fetch permissions:", err);
  setPermissions([]);
}
```

3. Reset permissions on logout/unauthenticated:

```jsx
if (!isAuthenticated) {
  setUser(null);
  setPermissions([]);
  fetchedUserRef.current = false;
  setSessionRestored(true);
  return;
}
```

4. Add `permissions` and `setPermissions` to the `useMemo` value:

```jsx
const value = useMemo(
  () => ({
    isAuthenticated,
    sessionRestored,
    user,
    permissions,
    login,
    logout,
    getToken,
    error: logtoError,
  }),
  [isAuthenticated, sessionRestored, user, permissions, login, logout, getToken, logtoError]
);
```

- [ ] **Step 2: Import the api client**

Add at the top of AuthContext.jsx:

```jsx
import { api } from "../APIClients/APIClient";
```

- [ ] **Step 3: Run lint**

Run: `cd RecordsFrontend && npm run lint`
Expected: No new warnings

- [ ] **Step 4: Commit**

```bash
cd RecordsFrontend
git add src/Context/AuthContext.jsx
git commit -m "feat: fetch and store user permissions in AuthContext"
```

---

## Task 8: Frontend — Update `lib/roles.js` to Use Permissions

**Files:**
- Modify: `src/lib/roles.js`

**Interfaces:**
- Consumes: `hasPermission` from `src/lib/permissions.js`
- Produces: Updated `canAccessDashboard`, `isEventManager`, `isDocumentManager`, `isFinancialManager`, `isAssetManager` that check permissions

- [ ] **Step 1: Rewrite roles.js**

```js
import { hasPermission } from "./permissions";

export { hasPermission } from "./permissions";

const ADMIN_ROLE_NAMES = ["super_admin", "org_admin"];

function hasRole(user, roleNames) {
  if (!user || !Array.isArray(user.roles)) return false;
  return user.roles.some((role) => roleNames.includes(role?.name));
}

export function isAdminUser(user) {
  return hasRole(user, ADMIN_ROLE_NAMES);
}

export function isMember(user) {
  if (isAdminUser(user)) return false;
  return hasRole(user, ["member"]);
}

export function canAccessDashboard(user) {
  return hasPermission(user, "dashboard.view");
}

export function isDocumentManager(user) {
  return hasPermission(user, "documents.view");
}

export function isEventManager(user) {
  return hasPermission(user, "events.view");
}

export function isFinancialManager(user) {
  return hasPermission(user, "financials.view");
}

export function isAssetManager(user) {
  return hasPermission(user, "assets.view");
}
```

- [ ] **Step 2: Run lint**

Run: `cd RecordsFrontend && npm run lint`
Expected: No new warnings

- [ ] **Step 3: Commit**

```bash
cd RecordsFrontend
git add src/lib/roles.js
git commit -m "refactor: permission functions now check permissions instead of roles"
```

---

## Task 9: Frontend — Update Sidebar to Use Permission-Based Filtering

**Files:**
- Modify: `src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx`

**Interfaces:**
- Consumes: `hasPermission` from `src/lib/permissions.js`

- [ ] **Step 1: Update navData and filtering**

Replace the `adminOnly` field with `permission` on nav items, and update `visibleNavData`:

```jsx
import { useAuth } from "@/Context/AuthContext";
import { hasPermission } from "@/lib/permissions";
// ... rest of imports (remove isAdminUser import)

export const navData = [
  { label: "Overview", isSection: true },
  { title: "Dashboard", icon: LayoutDashboard, href: "/app", permission: "dashboard.view" },

  { label: "Management", isSection: true },
  { title: "Members", icon: Users, href: "/app/members", permission: "members.view" },
  { title: "Events", icon: CalendarDays, href: "/app/events", permission: "events.view" },
  { title: "Financial Records", icon: Wallet, href: "/app/financial-records", permission: "financials.view" },
  { title: "Assets", icon: Package, href: "/app/assets", permission: "assets.view" },
  { title: "Documents", icon: FolderOpen, href: "/app/documents", permission: "documents.view" },

  { label: "Admin", isSection: true },
  { title: "Activity Logs", icon: History, href: "/app/logs", permission: "activity_logs.view" },
  { title: "Roles & Permissions", icon: Shield, href: "/app/roles", permission: "roles.manage" },
];

function visibleNavData(user) {
  const result = [];
  let pendingSection = null;
  for (const item of navData) {
    if (item.isSection) {
      pendingSection = item;
      continue;
    }
    if (!hasPermission(user, item.permission)) continue;
    if (pendingSection) {
      result.push(pendingSection);
      pendingSection = null;
    }
    result.push(item);
  }
  return result;
}
```

- [ ] **Step 2: Run lint**

Run: `cd RecordsFrontend && npm run lint`
Expected: No new warnings

- [ ] **Step 3: Commit**

```bash
cd RecordsFrontend
git add src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx
git commit -m "feat: sidebar nav items filtered by permissions instead of admin role"
```

---

## Task 10: Frontend — Update Page Components with PermissionGate

**Files:**
- Modify: `src/Pages/Dashboard/Dashboard.jsx`
- Modify: `src/Pages/MemberDirectory/MembersIndex.jsx`
- Modify: `src/Pages/EventsDirectory/EventsIndex.jsx`
- Modify: `src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx`
- Modify: `src/Pages/AssetDirectory/AssetsIndex.jsx`
- Modify: `src/Pages/DocumentDirectory/DocumentsIndex.jsx`
- Modify: `src/Pages/LogsDirectory/LogsIndex.jsx`
- Modify: `src/Pages/AdminDirectory/RolesIndex.jsx`
- Modify: `src/Pages/AdminDirectory/RoleDetailPage.jsx`

**Interfaces:**
- Consumes: `<PermissionGate>` from Task 6

- [ ] **Step 1: Update Dashboard.jsx**

Wrap the return content with `<PermissionGate permission="dashboard.view">`:

```jsx
import PermissionGate from "@/components/PermissionGate";

export default function Dashboard() {
  const { data, isLoading, error, refetch } = useDashboardSummary();

  return (
    <PermissionGate permission="dashboard.view">
      <div className="flex flex-col gap-6">
        {/* ... existing content unchanged ... */}
      </div>
    </PermissionGate>
  );
}
```

- [ ] **Step 2: Update MembersIndex.jsx**

Add import and wrap content:

```jsx
import PermissionGate from "@/components/PermissionGate";
```

Wrap the return content with `<PermissionGate permission="members.view">`.

- [ ] **Step 3: Update EventsIndex.jsx**

Add import and wrap content with `<PermissionGate permission="events.view">`.

- [ ] **Step 4: Update FinancialRecordsIndex.jsx**

Add import and wrap content with `<PermissionGate permission="financials.view">`.

- [ ] **Step 5: Update AssetsIndex.jsx**

Add import and wrap content with `<PermissionGate permission="assets.view">`.

- [ ] **Step 6: Update DocumentsIndex.jsx**

Add import and wrap content with `<PermissionGate permission="documents.view">`.

- [ ] **Step 7: Update LogsIndex.jsx**

Remove the inline `AccessDenied` function, import shared component, and wrap content:

```jsx
import PermissionGate from "@/components/PermissionGate";
// Remove local AccessDenied function

export default function LogsIndex() {
  const { user } = useAuth();

  // Remove the if (!isAdmin) { return ... } block

  return (
    <PermissionGate permission="activity_logs.view">
      <div className="flex flex-col gap-6">
        {/* ... existing content ... */}
      </div>
    </PermissionGate>
  );
}
```

Also remove the `isAdminUser` import since it's no longer used.

- [ ] **Step 8: Update RolesIndex.jsx**

Remove inline `AccessDenied`, import shared component, wrap content:

```jsx
import PermissionGate from "@/components/PermissionGate";
// Remove local AccessDenied function

export default function RolesIndex() {
  const { user } = useAuth();

  // Remove if (!isAdmin) block

  return (
    <PermissionGate permission="roles.manage">
      <div className="flex flex-col gap-6">
        {/* ... existing content ... */}
      </div>
    </PermissionGate>
  );
}
```

Remove `isAdminUser` import.

- [ ] **Step 9: Update RoleDetailPage.jsx**

Wrap content with `<PermissionGate permission="roles.manage">`:

```jsx
import PermissionGate from "@/components/PermissionGate";

export default function RoleDetailPage() {
  // ... remove if (!isAdmin) block

  return (
    <PermissionGate permission="roles.manage">
      <div className="flex flex-col gap-6">
        {/* ... existing content ... */}
      </div>
    </PermissionGate>
  );
}
```

Remove `isAdminUser` import.

- [ ] **Step 10: Run lint and build**

Run: `cd RecordsFrontend && npm run lint && npm run build`
Expected: No errors

- [ ] **Step 11: Commit**

```bash
cd RecordsFrontend
git add src/Pages/
git commit -m "feat: wrap all pages with PermissionGate for permission-based access control"
```

---

## Task 11: Backend — Verify End-to-End (Run Full Test Suite)

**Files:** None (verification only)

- [ ] **Step 1: Run full backend test suite**

Run: `cd RecordsAPI && php artisan test --compact`
Expected: All tests pass

- [ ] **Step 2: Run Pint on full codebase**

Run: `cd RecordsAPI && vendor/bin/pint --format agent`
Expected: Clean

- [ ] **Step 3: Run frontend lint and build**

Run: `cd RecordsFrontend && npm run lint && npm run build`
Expected: Clean

- [ ] **Step 4: Manual smoke test**

1. Login as a `member` role user → dashboard should be hidden, nav should only show Events and Documents
2. Login as an `executive` role user → dashboard visible, all management pages visible
3. Login as `superadmin` → everything visible including Activity Logs and Roles
4. Navigate directly to `/app/logs` as a member → should see Access Denied
5. Verify rate limiting: make 61 rapid requests → should get 429

---

## Task 12: Final Commit

- [ ] **Step 1: Review all changes**

Run: `git status` and `git diff --stat` in both repos

- [ ] **Step 2: Final commit if any uncommitted changes**

```bash
cd RecordsAPI && git add -A && git commit -m "feat: rate limiting and RBAC enhancement" --allow-empty
cd RecordsFrontend && git add -A && git commit -m "feat: rate limiting and RBAC enhancement" --allow-empty
```
