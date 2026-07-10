<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** Same admin-lockout invariant guarded in UserController. */
    private const ADMIN_PERMISSION = 'roles.manage';

    public function index()
    {
        return response()->json(Role::with('permissions')->get());
    }

    public function store(StoreRoleRequest $request)
    {
        // creating a role can't strip anyone's access, so no guard needed here
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($request->validated('permissions', []));

        return response()->json($role->load('permissions'), 201);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $newPermissions = $request->validated('permissions', []);

        $this->guardLastAdmin($role, $newPermissions);

        $role->syncPermissions($newPermissions);

        return response()->json($role->load('permissions'));
    }

    /**
     * The other lockout door (see docs/security/04-privilege-management.md):
     * editing a role's permissions can strip roles.manage from the last route
     * anyone had to it. Invariant: at least one user must still hold
     * roles.manage after this edit.
     */
    private function guardLastAdmin(Role $role, array $newPermissions): void
    {
        // the role keeps roles.manage, or never had it — this edit is harmless
        if (in_array(self::ADMIN_PERMISSION, $newPermissions, true)) {
            return;
        }
        if (!$role->hasPermissionTo(self::ADMIN_PERMISSION)) {
            return;
        }

        // this edit removes roles.manage from $role. Do any users still hold it
        // through a DIFFERENT admin-granting role? (Users whose only source was
        // $role would lose it.)
        $otherAdminRoles = Role::where('id', '!=', $role->id)
            ->whereHas('permissions', fn ($query) => $query->where('name', self::ADMIN_PERMISSION))
            ->pluck('name');

        $remainingAdmins = $otherAdminRoles->isNotEmpty()
            ? User::role($otherAdminRoles->all())->count()
            : 0;

        if ($remainingAdmins === 0) {
            throw ValidationException::withMessages([
                'permissions' => 'Cannot remove roles.manage from the last administrator role.',
            ]);
        }
    }
}
