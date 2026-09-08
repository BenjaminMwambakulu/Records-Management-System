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
