# Roles & Permissions Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin-only page for managing roles, assigning permissions to roles, and assigning roles to users.

**Architecture:** New `RoleController` with CRUD + permission sync + user assignment endpoints on the backend. New `useRolesDirectory` hook + 5 frontend components (list page, detail page, 3 dialogs) following existing patterns. Uses Spatie Permission directly (no new models).

**Tech Stack:** Laravel 12 / PHP 8.5 / Spatie Permission v8.3 / Pest (backend). React 18 / Vite / Tailwind csit-* tokens / Lucide icons (frontend).

**Spec:** `docs/superpowers/specs/2026-09-06-roles-permissions-design.md`

## Global Constraints

- Guard name for all roles/permissions: `logto` (not `web`)
- Admin check: `role:admin|superadmin,logto` middleware (backend), `isAdminUser(user)` (frontend)
- Cannot delete `superadmin` role; cannot remove last superadmin user
- No new database migrations needed — uses existing Spatie tables
- No changes to the seeder
- Backend tests use in-memory SQLite (`phpunit.xml`), `JwtTestHelper` for auth
- Frontend: `npm run lint` + `npm run build` (no JS test runner)
- No code comments unless needed; follow existing conventions exactly

---

### Task 1: Backend — RoleController, resources, form requests, routes, tests

**Files:**
- Create: `app/Http/Controllers/Api/RoleController.php`
- Create: `app/Http/Resources/RoleResource.php`
- Create: `app/Http/Resources/PermissionResource.php`
- Create: `app/Http/Requests/StoreRoleRequest.php`
- Create: `app/Http/Requests/UpdateRoleRequest.php`
- Create: `app/Http/Requests/SyncPermissionsRequest.php`
- Create: `app/Http/Requests/AssignRoleUserRequest.php`
- Modify: `routes/api.php` — add role routes
- Create: `tests/Feature/RoleApiTest.php`

**Interfaces:**
- Consumes: Spatie `Role` and `Permission` models (existing), `APIResponse` trait, `JwtTestHelper`, `User` model
- Produces: 9 endpoints (listed below), `RoleResource` (used by frontend hook), `PermissionResource`

#### Endpoints

| Route | Method | Middleware | Purpose |
|---|---|---|---|
| `GET /v1/roles` | GET | `role:admin\|superadmin,logto` | List all roles |
| `POST /v1/roles` | POST | `role:admin\|superadmin,logto` | Create role |
| `GET /v1/roles/{role}` | GET | `role:admin\|superadmin,logto` | Role detail |
| `PUT /v1/roles/{role}` | PUT | `role:admin\|superadmin,logto` | Update role |
| `DELETE /v1/roles/{role}` | DELETE | `role:admin\|superadmin,logto` | Delete role |
| `GET /v1/permissions` | GET | `role:admin\|superadmin,logto` | All permissions |
| `PUT /v1/roles/{role}/permissions` | PUT | `role:admin\|superadmin,logto` | Sync permissions |
| `POST /v1/roles/{role}/users` | POST | `role:admin\|superadmin,logto` | Assign role to user |
| `DELETE /v1/roles/{role}/users/{user}` | DELETE | `role:admin\|superadmin,logto` | Remove role from user |

- [ ] **Step 1: Create form request classes**

Create `app/Http/Requests/StoreRoleRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,guard_name:logto'],
        ];
    }
}
```

Create `app/Http/Requests/UpdateRoleRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id . ',id,guard_name:logto'],
        ];
    }
}
```

Create `app/Http/Requests/SyncPermissionsRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name,guard_name:logto'],
        ];
    }
}
```

Create `app/Http/Requests/AssignRoleUserRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
```

- [ ] **Step 2: Create API resources**

Create `app/Http/Resources/RoleResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'permissions_count' => $this->permissions->count(),
            'users_count' => $this->users->count(),
            'created_at' => $this->created_at,
        ];
    }
}
```

Create `app/Http/Resources/PermissionResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/** @mixin Permission */
class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
        ];
    }
}
```

- [ ] **Step 3: Create RoleController**

Create `app/Http/Controllers/Api/RoleController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoleUserRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncPermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Responses\APIResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use APIResponse;

    public function __construct()
    {
        $this->middleware('role:admin|superadmin,logto');
    }

    public function index(): JsonResponse
    {
        $roles = Role::with('permissions', 'users')->where('guard_name', 'logto')->get();
        return $this->success(RoleResource::collection($roles), 'Roles retrieved successfully');
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'logto',
        ]);
        return $this->success(new RoleResource($role->load('permissions', 'users')), 'Role created successfully', JsonResponse::HTTP_CREATED);
    }

    public function show(Role $role): JsonResponse
    {
        if ($role->guard_name !== 'logto') {
            return $this->error('Role not found', JsonResponse::HTTP_NOT_FOUND);
        }
        $role->load('permissions', 'users');
        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => PermissionResource::collection($role->permissions),
            'users' => $role->users->map(fn ($user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]),
            'created_at' => $role->created_at,
        ], 'Role retrieved successfully');
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($role->guard_name !== 'logto') {
            return $this->error('Role not found', JsonResponse::HTTP_NOT_FOUND);
        }
        $role->update(['name' => $request->validated('name')]);
        return $this->success(new RoleResource($role->load('permissions', 'users')), 'Role updated successfully');
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->guard_name !== 'logto') {
            return $this->error('Role not found', JsonResponse::HTTP_NOT_FOUND);
        }
        if ($role->name === 'superadmin') {
            return $this->error('Cannot delete the superadmin role', JsonResponse::HTTP_FORBIDDEN);
        }
        $role->delete();
        return $this->success(null, 'Role deleted successfully');
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'logto')->get();
        $grouped = $permissions->groupBy(fn ($p) => explode('.', $p->name)[0])->map(fn ($group) => $group->pluck('name')->values()->all());
        return $this->success($grouped, 'Permissions retrieved successfully');
    }

    public function syncPermissions(SyncPermissionsRequest $request, Role $role): JsonResponse
    {
        if ($role->guard_name !== 'logto') {
            return $this->error('Role not found', JsonResponse::HTTP_NOT_FOUND);
        }
        $permissionNames = $request->validated('permissions');
        $permissions = Permission::whereIn('name', $permissionNames)->where('guard_name', 'logto')->get();
        $role->syncPermissions($permissions);
        return $this->success(new RoleResource($role->fresh()->load('permissions', 'users')), 'Permissions updated successfully');
    }

    public function addUser(AssignRoleUserRequest $request, Role $role): JsonResponse
    {
        if ($role->guard_name !== 'logto') {
            return $this->error('Role not found', JsonResponse::HTTP_NOT_FOUND);
        }
        $user = User::findOrFail($request->validated('user_id'));
        if ($user->hasRole($role)) {
            return $this->error('User already has this role', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user->assignRole($role);
        return $this->success(new RoleResource($role->fresh()->load('permissions', 'users')), 'Role assigned successfully');
    }

    public function removeUser(Role $role, User $user): JsonResponse
    {
        if ($role->guard_name !== 'logto') {
            return $this->error('Role not found', JsonResponse::HTTP_NOT_FOUND);
        }
        if ($role->name === 'superadmin') {
            $superadminCount = User::role('superadmin', 'logto')->count();
            if ($superadminCount <= 1) {
                return $this->error('Cannot remove the last superadmin user', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        $user->removeRole($role);
        return $this->success(new RoleResource($role->fresh()->load('permissions', 'users')), 'Role removed successfully');
    }
}
```

- [ ] **Step 4: Register routes in api.php**

Add the following inside the `Route::prefix('v1')->middleware('auth:logto')->group(function () { ... })` block in `routes/api.php`, after the existing member role routes (after line 32):

```php
Route::apiResource('roles', RoleController::class)->middleware('role:admin|superadmin,logto');
Route::get('permissions', [RoleController::class, 'permissions'])->middleware('role:admin|superadmin,logto');
Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('role:admin|superadmin,logto');
Route::post('roles/{role}/users', [RoleController::class, 'addUser'])->middleware('role:admin|superadmin,logto');
Route::delete('roles/{role}/users/{user}', [RoleController::class, 'removeUser'])->middleware('role:admin|superadmin,logto');
```

Add the import at the top of `routes/api.php`:

```php
use App\Http\Controllers\Api\RoleController;
```

- [ ] **Step 5: Write feature tests**

Create `tests/Feature/RoleApiTest.php`:

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

function adminToken(array $keys, string $logtoId = 'logto-admin'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createAdminUser(): User
{
    $user = User::factory()->create(['logto_id' => 'logto-admin']);
    $role = Role::create(['name' => 'admin', 'guard_name' => 'logto']);
    $user->assignRole($role);
    return $user;
}

function createSuperadminUser(): User
{
    $user = User::factory()->create(['logto_id' => 'logto-superadmin']);
    $role = Role::create(['name' => 'superadmin', 'guard_name' => 'logto']);
    $user->assignRole($role);
    return $user;
}

test('admin can list all roles', function () {
    createAdminUser();
    Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    Role::create(['name' => 'member', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('non-admin cannot list roles', function () {
    $user = User::factory()->create(['logto_id' => 'logto-member']);
    $role = Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $user->assignRole($role);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys, 'logto-member'))
        ->getJson('/api/v1/roles')
        ->assertForbidden();
});

test('admin can create a role', function () {
    createAdminUser();

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->postJson('/api/v1/roles', ['name' => 'moderator'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'moderator');

    $this->assertDatabaseHas('roles', ['name' => 'moderator', 'guard_name' => 'logto']);
});

test('admin cannot create duplicate role', function () {
    createAdminUser();
    Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->postJson('/api/v1/roles', ['name' => 'executive'])
        ->assertUnprocessable();
});

test('admin can get role detail', function () {
    createAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $perm = Permission::create(['name' => 'events.view', 'guard_name' => 'logto']);
    $role->givePermissionTo($perm);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->getJson('/api/v1/roles/'.$role->id)
        ->assertOk()
        ->assertJsonPath('data.name', 'executive')
        ->assertJsonCount(1, 'data.permissions');
});

test('admin can update a role', function () {
    createAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->putJson('/api/v1/roles/'.$role->id, ['name' => 'officer'])
        ->assertOk()
        ->assertJsonPath('data.name', 'officer');

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'officer']);
});

test('admin can delete a non-superadmin role', function () {
    createAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id)
        ->assertOk();

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('admin cannot delete superadmin role', function () {
    createAdminUser();
    $role = Role::create(['name' => 'superadmin', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id)
        ->assertForbidden();
});

test('admin can get permissions grouped by module', function () {
    createAdminUser();
    Permission::create(['name' => 'members.view', 'guard_name' => 'logto']);
    Permission::create(['name' => 'members.create', 'guard_name' => 'logto']);
    Permission::create(['name' => 'events.view', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('data.members.0', 'members.view')
        ->assertJsonPath('data.members.1', 'members.create')
        ->assertJsonPath('data.events.0', 'events.view');
});

test('admin can sync permissions for a role', function () {
    createAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    Permission::create(['name' => 'events.view', 'guard_name' => 'logto']);
    Permission::create(['name' => 'events.create', 'guard_name' => 'logto']);
    Permission::create(['name' => 'members.view', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->putJson('/api/v1/roles/'.$role->id.'/permissions', [
            'permissions' => ['events.view', 'events.create'],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data.permissions');

    $this->assertDatabaseHas('role_has_permissions', ['role_id' => $role->id]);
});

test('admin can assign role to user', function () {
    createAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $target = User::factory()->create(['logto_id' => 'logto-target']);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->postJson('/api/v1/roles/'.$role->id.'/users', ['user_id' => $target->id])
        ->assertOk();

    $this->assertTrue($target->fresh()->hasRole($role));
});

test('admin can remove role from user', function () {
    createAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $target = User::factory()->create(['logto_id' => 'logto-target']);
    $target->assignRole($role);

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id.'/users/'.$target->id)
        ->assertOk();

    $this->assertFalse($target->fresh()->hasRole($role));
});

test('admin cannot remove last superadmin', function () {
    $superadmin = createSuperadminUser();
    $admin = createAdminUser();
    $role = Role::where('name', 'superadmin')->where('guard_name', 'logto')->first();

    $this->withHeader('Authorization', 'Bearer '.adminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id.'/users/'.$superadmin->id)
        ->assertUnprocessable();
});
```

- [ ] **Step 6: Run tests**

Run: `vendor/bin/pest --compact`
Expected: All tests pass.

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: Clean.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/RoleController.php app/Http/Resources/RoleResource.php app/Http/Resources/PermissionResource.php app/Http/Requests/StoreRoleRequest.php app/Http/Requests/UpdateRoleRequest.php app/Http/Requests/SyncPermissionsRequest.php app/Http/Requests/AssignRoleUserRequest.php routes/api.php tests/Feature/RoleApiTest.php
git commit -m "feat: add roles & permissions management API"
```

---

### Task 2: Frontend — useRolesDirectory hook + routes

**Files:**
- Create: `src/hooks/useRolesDirectory.js`
- Modify: `src/App.jsx` — add routes
- Modify: `src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx` — add nav item

**Interfaces:**
- Consumes: `api` from `@/APIClients/APIClient`, `isAdminUser` from `@/lib/roles`, `useAuth` from `@/Context/AuthContext`
- Produces: `useRolesDirectory()` hook (returns roles, permissions, currentRole, CRUD actions) — used by all frontend pages in Task 3

- [ ] **Step 1: Create useRolesDirectory hook**

Create `src/hooks/useRolesDirectory.js`:

```js
import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/APIClients/APIClient";

export default function useRolesDirectory() {
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState({});
  const [currentRole, setCurrentRole] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const cancelledRef = useRef(false);

  const fetchRoles = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);
    api.get("/v1/roles")
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        setRoles(Array.isArray(body) ? body : body?.data ?? []);
      })
      .catch((err) => {
        if (!cancelledRef.current) setError(err);
      })
      .finally(() => {
        if (!cancelledRef.current) setIsLoading(false);
      });
  }, []);

  const fetchPermissions = useCallback(() => {
    api.get("/v1/permissions")
      .then((response) => {
        const body = response.data;
        setPermissions(body ?? {});
      })
      .catch(() => {});
  }, []);

  const fetchRole = useCallback((id) => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);
    api.get(`/v1/roles/${id}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        setCurrentRole(body);
      })
      .catch((err) => {
        if (!cancelledRef.current) setError(err);
      })
      .finally(() => {
        if (!cancelledRef.current) setIsLoading(false);
      });
  }, []);

  useEffect(() => {
    fetchRoles();
    fetchPermissions();
    return () => { cancelledRef.current = true; };
  }, [fetchRoles, fetchPermissions]);

  const createRole = useCallback(async (data) => {
    setIsSubmitting(true);
    try {
      const response = await api.post("/v1/roles", data);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRoles]);

  const updateRole = useCallback(async (id, data) => {
    setIsSubmitting(true);
    try {
      const response = await api.put(`/v1/roles/${id}`, data);
      await fetchRoles();
      if (currentRole?.id === id) await fetchRole(id);
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRoles, fetchRole, currentRole]);

  const deleteRole = useCallback(async (id) => {
    setIsSubmitting(true);
    try {
      const response = await api.delete(`/v1/roles/${id}`);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRoles]);

  const syncPermissions = useCallback(async (roleId, permissionIds) => {
    setIsSubmitting(true);
    try {
      const response = await api.put(`/v1/roles/${roleId}/permissions`, { permissions: permissionIds });
      if (currentRole?.id === roleId) await fetchRole(roleId);
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRole, currentRole]);

  const assignRoleToUser = useCallback(async (roleId, userId) => {
    setIsSubmitting(true);
    try {
      const response = await api.post(`/v1/roles/${roleId}/users`, { user_id: userId });
      if (currentRole?.id === roleId) await fetchRole(roleId);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRole, fetchRoles, currentRole]);

  const removeRoleFromUser = useCallback(async (roleId, userId) => {
    setIsSubmitting(true);
    try {
      const response = await api.delete(`/v1/roles/${roleId}/users/${userId}`);
      if (currentRole?.id === roleId) await fetchRole(roleId);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRole, fetchRoles, currentRole]);

  return {
    roles,
    permissions,
    currentRole,
    isLoading,
    isSubmitting,
    error,
    fetchRoles,
    fetchRole,
    fetchPermissions,
    createRole,
    updateRole,
    deleteRole,
    syncPermissions,
    assignRoleToUser,
    removeRoleFromUser,
  };
}
```

- [ ] **Step 2: Add routes to App.jsx**

Add to `src/App.jsx` inside the `<Route path="/" element={<ProtectedRoute><Layout /></ProtectedRoute>}>` block, after the logs route:

```jsx
<Route path="roles" element={<RolesIndex />} />
<Route path="roles/:id" element={<RoleDetailPage />} />
```

Add imports at the top:

```jsx
import RolesIndex from "./Pages/AdminDirectory/RolesIndex";
import RoleDetailPage from "./Pages/AdminDirectory/RoleDetailPage";
```

- [ ] **Step 3: Add sidebar nav item**

In `src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx`:

Add `Shield` to the lucide-react import (line 11).

Add to `navData` Admin section, after Activity Logs:

```jsx
{ title: "Roles & Permissions", icon: Shield, href: "/roles", adminOnly: true },
```

- [ ] **Step 4: Lint + build**

Run: `npm run lint` — no new warnings.
Run: `npm run build` — succeeds.

- [ ] **Step 5: Commit**

```bash
git add src/hooks/useRolesDirectory.js src/App.jsx src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx
git commit -m "feat: add useRolesDirectory hook, routes, and sidebar nav"
```

---

### Task 3: Frontend — Pages and dialogs

**Files:**
- Create: `src/Pages/AdminDirectory/RolesIndex.jsx`
- Create: `src/Pages/AdminDirectory/RoleDetailPage.jsx`
- Create: `src/Pages/AdminDirectory/RoleFormDialog.jsx`
- Create: `src/Pages/AdminDirectory/DeleteRoleDialog.jsx`
- Create: `src/Pages/AdminDirectory/AssignUserDialog.jsx`

**Interfaces:**
- Consumes: `useRolesDirectory` hook (Task 2), `useAuth` from `@/Context/AuthContext`, `isAdminUser` from `@/lib/roles`, `extractErrorMessage` from `@/lib/errors`, UI primitives (`button`, `input`, `card`, `table`, `skeleton`, `empty`, `dialog`, `badge`)
- Produces: `RolesIndex` (list page), `RoleDetailPage` (detail page with permission matrix + user list)

- [ ] **Step 1: Create RolesIndex page**

Create `src/Pages/AdminDirectory/RolesIndex.jsx`:

```jsx
import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Plus, Shield, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useAuth } from "@/Context/AuthContext";
import { isAdminUser } from "@/lib/roles";
import { extractErrorMessage } from "@/lib/errors";
import useRolesDirectory from "@/hooks/useRolesDirectory";
import RoleFormDialog from "./RoleFormDialog";
import DeleteRoleDialog from "./DeleteRoleDialog";

function AccessDenied() {
  return (
    <Card className="border-csit-border bg-white">
      <Empty className="py-16">
        <EmptyHeader>
          <EmptyMedia variant="icon"><Shield /></EmptyMedia>
          <EmptyTitle>Access restricted</EmptyTitle>
          <EmptyDescription>
            You do not have permission to manage roles. Contact an administrator.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    </Card>
  );
}

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 4 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-4 w-32" /></TableCell>
          <TableCell><Skeleton className="h-4 w-16" /></TableCell>
          <TableCell><Skeleton className="h-4 w-16" /></TableCell>
          <TableCell><Skeleton className="h-4 w-24" /></TableCell>
          <TableCell><Skeleton className="h-4 w-20" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function RolesIndex() {
  const { user } = useAuth();
  const isAdmin = isAdminUser(user);
  const navigate = useNavigate();
  const { roles, isLoading, error, createRole, deleteRole, isSubmitting } = useRolesDirectory();
  const [createOpen, setCreateOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);

  if (!isAdmin) {
    return (
      <div className="flex flex-col gap-6">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Roles & Permissions</h1>
          <p className="mt-1 text-sm text-csit-text-muted">Manage roles and their permissions.</p>
        </div>
        <AccessDenied />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Roles & Permissions</h1>
          <p className="mt-1 text-sm text-csit-text-muted">Manage roles and their permissions.</p>
        </div>
        <Button type="button" onClick={() => setCreateOpen(true)}>
          <Plus />
          Create role
        </Button>
      </div>

      <Card className="border-csit-border bg-white">
        {error ? (
          <div className="flex flex-col items-center gap-3 py-12">
            <p className="text-sm text-rose-600">{extractErrorMessage(error)}</p>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="px-4">Name</TableHead>
                <TableHead className="px-4">Permissions</TableHead>
                <TableHead className="px-4">Users</TableHead>
                <TableHead className="px-4">Created</TableHead>
                <TableHead className="px-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            {isLoading ? (
              <TableSkeleton />
            ) : roles.length === 0 ? (
              <TableBody>
                <TableRow>
                  <TableCell colSpan={5}>
                    <Empty className="py-12">
                      <EmptyHeader>
                        <EmptyMedia variant="icon"><Shield /></EmptyMedia>
                        <EmptyTitle>No roles</EmptyTitle>
                        <EmptyDescription>Create your first role to get started.</EmptyDescription>
                      </EmptyHeader>
                    </Empty>
                  </TableCell>
                </TableRow>
              </TableBody>
            ) : (
              <TableBody>
                {roles.map((role) => (
                  <TableRow
                    key={role.id}
                    className="cursor-pointer"
                    onClick={() => navigate(`/roles/${role.id}`)}
                  >
                    <TableCell className="px-4 font-medium text-csit-text">{role.name}</TableCell>
                    <TableCell className="px-4">
                      <span className="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700">
                        {role.permissions_count}
                      </span>
                    </TableCell>
                    <TableCell className="px-4">
                      <span className="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700">
                        {role.users_count}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 text-sm text-csit-text-muted">
                      {role.created_at ? new Date(role.created_at).toLocaleDateString() : "—"}
                    </TableCell>
                    <TableCell className="px-4 text-right">
                      {role.name !== "superadmin" ? (
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={(e) => { e.stopPropagation(); setDeleteTarget(role); }}
                          aria-label={`Delete ${role.name}`}
                          className="hover:bg-rose-50 hover:text-rose-600"
                        >
                          <Trash2 className="text-csit-text-muted" />
                        </Button>
                      ) : null}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            )}
          </Table>
        )}
      </Card>

      <RoleFormDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        onSubmit={createRole}
        isSubmitting={isSubmitting}
      />

      <DeleteRoleDialog
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}
        role={deleteTarget}
        onDelete={deleteRole}
        isSubmitting={isSubmitting}
      />
    </div>
  );
}
```

- [ ] **Step 2: Create RoleFormDialog**

Create `src/Pages/AdminDirectory/RoleFormDialog.jsx`:

```jsx
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { extractErrorMessage } from "@/lib/errors";

export default function RoleFormDialog({
  open,
  onOpenChange,
  role,
  onSubmit,
  isSubmitting,
}) {
  const isEdit = Boolean(role);
  const [name, setName] = useState("");
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!open) return;
    setName(role?.name ?? "");
    setError(null);
  }, [open, role]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);
    try {
      await onSubmit({ name: name.trim() });
      onOpenChange(false);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit role" : "Create role"}</DialogTitle>
          <DialogDescription>
            {isEdit ? "Update the role name." : "Add a new role to the system."}
          </DialogDescription>
        </DialogHeader>

        <form id="role-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Name
            <Input
              required
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="e.g. moderator"
            />
          </label>
        </form>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="role-form" disabled={isSubmitting}>
            {isSubmitting ? (isEdit ? "Saving…" : "Creating…") : isEdit ? "Save changes" : "Create role"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 3: Create DeleteRoleDialog**

Create `src/Pages/AdminDirectory/DeleteRoleDialog.jsx`:

```jsx
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { extractErrorMessage } from "@/lib/errors";

export default function DeleteRoleDialog({
  open,
  onOpenChange,
  role,
  onDelete,
  isSubmitting,
}) {
  const [error, setError] = useState(null);

  const handleDelete = async () => {
    setError(null);
    try {
      await onDelete(role.id);
      onOpenChange(false);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Delete role</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete{" "}
            <span className="font-medium text-foreground">{role?.name}</span>?
            {role?.users_count > 0 ? ` This role is assigned to ${role.users_count} user(s).` : ""}
            {" "}This action cannot be undone.
          </DialogDescription>
        </DialogHeader>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button type="button" variant="destructive" onClick={handleDelete} disabled={isSubmitting}>
            {isSubmitting ? "Deleting…" : "Delete role"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 4: Create AssignUserDialog**

Create `src/Pages/AdminDirectory/AssignUserDialog.jsx`:

```jsx
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { api } from "@/APIClients/APIClient";
import { extractErrorMessage } from "@/lib/errors";

export default function AssignUserDialog({
  open,
  onOpenChange,
  onAssign,
  isSubmitting,
}) {
  const [search, setSearch] = useState("");
  const [users, setUsers] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!open) {
      setSearch("");
      setUsers([]);
      setError(null);
      return;
    }
    const timer = setTimeout(() => {
      if (!search.trim()) { setUsers([]); return; }
      setIsSearching(true);
      api.get(`/v1/members?search=${encodeURIComponent(search.trim())}&per_page=10`)
        .then((response) => {
          const body = response.data;
          setUsers(Array.isArray(body) ? body : body?.data ?? []);
        })
        .catch((err) => setError(extractErrorMessage(err)))
        .finally(() => setIsSearching(false));
    }, 400);
    return () => clearTimeout(timer);
  }, [search, open]);

  const handleAssign = async (userId) => {
    setError(null);
    try {
      await onAssign(userId);
      onOpenChange(false);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Assign role to user</DialogTitle>
          <DialogDescription>Search for a user to assign this role to.</DialogDescription>
        </DialogHeader>

        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Search by name or email…"
        />

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <div className="flex flex-col gap-1 max-h-60 overflow-y-auto">
          {isSearching ? (
            <p className="py-4 text-center text-sm text-csit-text-muted">Searching…</p>
          ) : users.length === 0 ? (
            <p className="py-4 text-center text-sm text-csit-text-muted">
              {search.trim() ? "No users found." : "Type to search for users."}
            </p>
          ) : (
            users.map((u) => (
              <button
                key={u.id}
                type="button"
                onClick={() => handleAssign(u.id)}
                className="flex items-center gap-3 rounded-lg border border-csit-border px-3 py-2 text-left text-sm hover:bg-muted/50 transition-colors"
              >
                <div className="min-w-0">
                  <p className="font-medium text-csit-text">{u.first_name} {u.last_name}</p>
                  <p className="truncate text-xs text-csit-text-muted">{u.email}</p>
                </div>
              </button>
            ))
          )}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 5: Create RoleDetailPage**

Create `src/Pages/AdminDirectory/RoleDetailPage.jsx`:

```jsx
import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { ArrowLeft, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/Context/AuthContext";
import { isAdminUser } from "@/lib/roles";
import { extractErrorMessage } from "@/lib/errors";
import useRolesDirectory from "@/hooks/useRolesDirectory";
import RoleFormDialog from "./RoleFormDialog";
import DeleteRoleDialog from "./DeleteRoleDialog";
import AssignUserDialog from "./AssignUserDialog";

function ModuleSection({ module, permissions, selected, onToggle }) {
  const allChecked = permissions.every((p) => selected.includes(p));
  const someChecked = permissions.some((p) => selected.includes(p));

  const toggleAll = () => {
    if (allChecked) {
      onToggle(selected.filter((s) => !permissions.includes(s)));
    } else {
      const merged = [...new Set([...selected, ...permissions])];
      onToggle(merged);
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          checked={allChecked}
          ref={(el) => { if (el) el.indeterminate = someChecked && !allChecked; }}
          onChange={toggleAll}
          className="h-4 w-4 rounded border-input accent-primary"
        />
        <span className="text-sm font-medium text-csit-text capitalize">{module}</span>
        <span className="text-xs text-csit-text-muted">
          {permissions.filter((p) => selected.includes(p)).length}/{permissions.length}
        </span>
      </div>
      <div className="ml-6 flex flex-wrap gap-2">
        {permissions.map((perm) => (
          <label key={perm} className="flex items-center gap-1.5 text-xs text-csit-text-muted">
            <input
              type="checkbox"
              checked={selected.includes(perm)}
              onChange={() => {
                onToggle(
                  selected.includes(perm)
                    ? selected.filter((s) => s !== perm)
                    : [...selected, perm]
                );
              }}
              className="h-3.5 w-3.5 rounded border-input accent-primary"
            />
            {perm.split(".")[1]}
          </label>
        ))}
      </div>
    </div>
  );
}

export default function RoleDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const isAdmin = isAdminUser(user);
  const {
    currentRole, isLoading, error, permissions, isSubmitting,
    fetchRole, updateRole, deleteRole, syncPermissions, assignRoleToUser, removeRoleFromUser,
  } = useRolesDirectory();
  const [selectedPerms, setSelectedPerms] = useState([]);
  const [editOpen, setEditOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [assignOpen, setAssignOpen] = useState(false);

  useEffect(() => {
    if (id) fetchRole(id);
  }, [id, fetchRole]);

  useEffect(() => {
    if (currentRole?.permissions) {
      setSelectedPerms(currentRole.permissions.map((p) => p.name));
    }
  }, [currentRole]);

  if (!isAdmin) {
    return (
      <div className="flex flex-col gap-6">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Role Detail</h1>
          <p className="mt-1 text-sm text-csit-text-muted">You do not have permission to view this page.</p>
        </div>
      </div>
    );
  }

  if (isLoading && !currentRole) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col gap-6">
        <Button variant="ghost" size="sm" onClick={() => navigate("/roles")} className="w-fit">
          <ArrowLeft /> Back to roles
        </Button>
        <Card className="border-csit-border bg-white p-6">
          <p className="text-sm text-rose-600">{extractErrorMessage(error)}</p>
        </Card>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => navigate("/roles")} aria-label="Back">
            <ArrowLeft />
          </Button>
          <div>
            <h1 className="text-2xl font-semibold text-csit-text">{currentRole?.name}</h1>
            <p className="mt-1 text-sm text-csit-text-muted">
              {currentRole?.permissions?.length ?? 0} permissions · {currentRole?.users?.length ?? 0} users
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button type="button" variant="outline" onClick={() => setEditOpen(true)}>
            Edit
          </Button>
          {currentRole?.name !== "superadmin" ? (
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(currentRole)}>
              <Trash2 /> Delete
            </Button>
          ) : null}
        </div>
      </div>

      <Card className="border-csit-border bg-white p-6">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-csit-text-muted">Permissions</h2>
        <div className="flex flex-col gap-4">
          {Object.entries(permissions).map(([module, perms]) => (
            <ModuleSection
              key={module}
              module={module}
              permissions={perms}
              selected={selectedPerms}
              onToggle={setSelectedPerms}
            />
          ))}
        </div>
        <div className="mt-4 flex justify-end">
          <Button
            type="button"
            onClick={() => syncPermissions(currentRole.id, selectedPerms)}
            disabled={isSubmitting}
          >
            {isSubmitting ? "Saving…" : "Save permissions"}
          </Button>
        </div>
      </Card>

      <Card className="border-csit-border bg-white p-6">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-csit-text-muted">Assigned users</h2>
          <Button type="button" variant="outline" size="sm" onClick={() => setAssignOpen(true)}>
            <Plus /> Add user
          </Button>
        </div>
        {currentRole?.users?.length === 0 ? (
          <p className="py-4 text-center text-sm text-csit-text-muted">No users assigned to this role.</p>
        ) : (
          <div className="flex flex-col gap-2">
            {currentRole?.users?.map((u) => (
              <div key={u.id} className="flex items-center justify-between gap-3 rounded-lg border border-csit-border px-3 py-2">
                <div className="min-w-0">
                  <p className="text-sm font-medium text-csit-text">{u.first_name} {u.last_name}</p>
                  <p className="truncate text-xs text-csit-text-muted">{u.email}</p>
                </div>
                {currentRole?.name !== "superadmin" || (currentRole?.users?.length ?? 0) > 1 ? (
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => removeRoleFromUser(currentRole.id, u.id)}
                    aria-label={`Remove ${u.first_name}`}
                    className="hover:bg-rose-50 hover:text-rose-600"
                  >
                    <Trash2 className="text-csit-text-muted" />
                  </Button>
                ) : null}
              </div>
            ))}
          </div>
        )}
      </Card>

      <RoleFormDialog
        open={editOpen}
        onOpenChange={setEditOpen}
        role={currentRole}
        onSubmit={(data) => updateRole(currentRole.id, data)}
        isSubmitting={isSubmitting}
      />

      <DeleteRoleDialog
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}
        role={deleteTarget}
        onDelete={deleteRole}
        isSubmitting={isSubmitting}
      />

      <AssignUserDialog
        open={assignOpen}
        onOpenChange={setAssignOpen}
        onAssign={(userId) => assignRoleToUser(currentRole.id, userId)}
        isSubmitting={isSubmitting}
      />
    </div>
  );
}
```

- [ ] **Step 6: Lint + build**

Run: `npm run lint` — no new warnings.
Run: `npm run build` — succeeds.

- [ ] **Step 7: Commit**

```bash
git add src/Pages/AdminDirectory/RolesIndex.jsx src/Pages/AdminDirectory/RoleDetailPage.jsx src/Pages/AdminDirectory/RoleFormDialog.jsx src/Pages/AdminDirectory/DeleteRoleDialog.jsx src/Pages/AdminDirectory/AssignUserDialog.jsx
git commit -m "feat: add roles & permissions management pages"
```
