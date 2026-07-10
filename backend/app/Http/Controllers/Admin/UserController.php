<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * The permission that guards this admin panel itself. If no user holds it,
     * the role-management UI becomes permanently unreachable.
     */
    private const ADMIN_PERMISSION = 'roles.manage';

    public function index(Request $request)
    {
        $search = $request->query('search');

        $users = User::with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($users);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        return response()->json($user->load('roles'));
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user)
    {
        $newRoles = $request->validated('roles', []);

        $this->guardLastAdmin($user, $newRoles);

        $user->syncRoles($newRoles);

        return response()->json($user->load('roles'));
    }

    /**
     * Invariant: at least one user must always hold ADMIN_PERMISSION. Reject a
     * role change that would strip it from the final admin and lock everyone
     * out of this panel.
     */
    private function guardLastAdmin(User $user, array $newRoles): void
    {
        // Would the user KEEP admin access after this change? True if any role
        // they'd end up with grants the permission. hasPermissionTo() walks a
        // role's permissions for us.
        $keepsAdmin = Role::whereIn('name', $newRoles)->get()
            ->contains(fn (Role $role) => $role->hasPermissionTo(self::ADMIN_PERMISSION));

        if ($keepsAdmin) {
            return;
        }

        // This change removes admin from $user. Is anyone else still an admin?
        // The permission() scope (from Spatie's HasRoles) filters users who hold
        // the permission through any of their roles.
        $otherAdmins = User::permission(self::ADMIN_PERMISSION)
            ->where('id', '!=', $user->id)
            ->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages([
                'roles' => 'Cannot remove the last administrator.',
            ]);
        }
    }
}
