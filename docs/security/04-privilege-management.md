# Privilege management & admin lockout

RBAC gives some accounts the power to grant/revoke access. The risk unique to
that power is **locking everyone out of it** — reaching a state where no account
can administer roles anymore, which can only be fixed with direct database
access. This doc covers that class of risk for the `roles.manage`-gated admin.

## Why it matters

Every admin route here is guarded by `permission:roles.manage`
(`GET/POST/PUT /roles`, `GET /permissions`, `GET/PUT /users*`). If the number of
accounts holding `roles.manage` ever hits **zero**, those routes return 403 for
everyone — permanently, from inside the app. There's no "forgot my admin"
recovery flow; you'd be editing `model_has_roles` by hand in psql. For a real
deployment that's a Sev-1 incident triggered by a single careless click.

## Real-world attack / accident example

Not really an "attacker" scenario — it's a **self-inflicted footgun**, which is
more likely than malice:

1. There's one admin account (`admin@…`), holding the `admin` role, which is the
   only role granting `roles.manage`.
2. The admin opens the Users screen, means to demote a *colleague*, and by
   mistake removes the `admin` role from **their own** row (or from the last
   remaining admin).
3. `syncRoles([])` runs. Now nobody has `roles.manage`.
4. The Users and Roles screens 403 for everyone. RBAC is unmanageable without
   shell access to the DB.

The same end-state can be reached deliberately by a disgruntled admin as a
parting act of sabotage — "salt the earth" on the way out.

## How this project mitigates it

`UserController::guardLastAdmin()` runs before `syncRoles()` on
`PUT /users/{user}/roles`. It enforces one invariant:

> At least one user must always hold the `roles.manage` **permission**.

The logic (see `backend/app/Http/Controllers/Admin/UserController.php`):

1. Would the user still hold `roles.manage` after this change? (Does any role in
   the incoming set grant it?) If yes → safe, allow.
2. If the change strips `roles.manage` from this user, count *other* users who
   still hold it (`User::permission('roles.manage')->where('id','!=',$user->id)`).
3. If that count is `0`, reject with 422 "Cannot remove the last administrator."

Two deliberate design points:

- **Checked on the permission, not the `admin` role.** A future second
  admin-capable role (e.g. `super`) would automatically count as an admin — the
  guard tracks the actual capability, not one hard-coded role name. This mirrors
  the app-wide golden rule: check permissions, never roles.
- **Zero-holders is the invariant, not "you can't demote yourself."** Removing
  your own admin is fine *if another admin exists* (recoverable); it's only
  blocked when you're the last one. One rule covers both "removed myself" and
  "removed the last colleague."

## Residual risk (known gaps)

- **The role-editing path is NOT yet guarded.** The invariant is enforced on
  *assigning roles to users*, but `RoleController::update` (editing a role's
  permissions) is not. An admin who edits the `admin` role and unticks
  `roles.manage` orphans everyone whose access came through that role — same
  lockout, different door. **This must be guarded when the role-management UI
  (RolesView) ships** — the same "at least one holder" invariant, checked
  against the post-edit permission set. Tracked in `docs/backlog.md`.
- **Race condition.** Two concurrent requests each removing a *different* one of
  the last two admins can both pass the check and land zero. Not row-locked.
  Negligible for a single/few-admin install; if it matters, wrap the check +
  `syncRoles` in a transaction with a row lock. Tracked in `docs/backlog.md`.
- **No audit trail.** Role changes aren't logged, so a malicious or mistaken
  privilege change can't be traced after the fact. Out of scope for R1.
