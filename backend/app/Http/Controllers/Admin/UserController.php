<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Mail\UserInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

    public function store(StoreUserRequest $request)
    {
        // Invite-only: the new account gets a random, unusable password that
        // nobody knows (so it can't be logged into) and is flagged pending
        // (must_change_password). The user sets a real password via the emailed
        // invitation link, which clears the flag. See sendInvitation().
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make(Str::random(40)),
            'must_change_password' => true,
        ]);

        $user->syncRoles($request->validated('roles', []));

        $this->sendInvitation($user);

        // refresh so DB-defaulted columns (is_active) are reflected in the payload
        return response()->json($user->refresh()->load('roles'), 201);
    }

    /**
     * Re-send the invitation link (e.g. the first one expired or was lost). Only
     * valid while the user is still pending — an already-activated account
     * doesn't get invited again.
     */
    public function resendInvitation(User $user)
    {
        if (! $user->must_change_password) {
            throw ValidationException::withMessages([
                'user' => 'This user has already set their password.',
            ]);
        }

        $this->sendInvitation($user);

        return response()->json(['message' => 'Invitation sent']);
    }

    /**
     * Mint an invitation token (48h, via the 'invitations' broker) and email a
     * set-password link pointing at the SPA. The token is stored hashed in
     * password_reset_tokens and consumed when they set their password.
     */
    private function sendInvitation(User $user): void
    {
        $token = Password::broker('invitations')->createToken($user);

        $url = config('app.frontend_url').'/set-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);

        Mail::to($user->email)->send(new UserInvitation($user, $url));
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
     * Activate or deactivate a user. Deactivating refuses them at login while
     * keeping their row (and its audit references) intact.
     */
    public function setActive(Request $request, User $user)
    {
        $active = $request->validate([
            'active' => ['required', 'boolean'],
        ])['active'];

        if (! $active) {
            // You can't switch off the account you're currently signed in with.
            if ($user->is($request->user())) {
                throw ValidationException::withMessages([
                    'active' => 'You cannot deactivate your own account.',
                ]);
            }

            $this->guardLastActiveAdmin($user);
        }

        $user->update(['is_active' => $active]);

        return response()->json($user->load('roles'));
    }

    /**
     * Invariant: at least one ACTIVE user must always hold ADMIN_PERMISSION.
     * Reject a role change that would strip it from the final admin and lock
     * everyone out of this panel.
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

        if ($this->otherActiveAdminCount($user) === 0) {
            throw ValidationException::withMessages([
                'roles' => 'Cannot remove the last administrator.',
            ]);
        }
    }

    /**
     * Same invariant, enforced on deactivation: don't switch off the last
     * active admin (a deactivated admin can't log in, so they don't count).
     */
    private function guardLastActiveAdmin(User $user): void
    {
        if (! $user->hasPermissionTo(self::ADMIN_PERMISSION)) {
            return; // not an admin — deactivating them can't cause a lockout
        }

        if ($this->otherActiveAdminCount($user) === 0) {
            throw ValidationException::withMessages([
                'active' => 'Cannot deactivate the last administrator.',
            ]);
        }
    }

    /**
     * How many OTHER active users still hold ADMIN_PERMISSION. The permission()
     * scope (from Spatie's HasRoles) filters users who hold the permission
     * through any of their roles; we additionally require them to be active.
     */
    private function otherActiveAdminCount(User $user): int
    {
        return User::permission(self::ADMIN_PERMISSION)
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->count();
    }
}
