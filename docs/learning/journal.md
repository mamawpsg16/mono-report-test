# Learning journal

## R1 milestone 1–2 — RBAC foundation (spatie/laravel-permission)

- **Guards** — a Laravel app can have multiple independent authentication
  systems (guards) coexisting. This app only has one (`web`), and Sanctum's
  stateful SPA auth authenticates through that same `web` guard under the
  hood — "that's the only guard we have, and it's Sanctum currently on the
  web guard." `guard_name` on spatie's tables exists to scope roles/
  permissions to a specific guard, which matters once an app has more than
  one. See `backend/config/auth.php`, `backend/config/sanctum.php`.
- **`model_has_roles` vs `model_has_permissions`** — "model has role is if
  the user has role, and the has-permission is for when a user needs to
  bypass that and have it directly." More precisely: `model_has_permissions`
  is *additive*, not an override — `can()` checks both tables and returns
  true if either matches; a direct grant can't revoke what a role already
  gives. See `backend/database/seeders/UserRoleSeeder.php`.
- **Permission taxonomy: CRUD grid vs. single gate** — a business-domain
  module (`customers`) gets a full `view/create/update/delete` grid because
  view-only vs. full-edit are genuinely different, legitimate personas. A
  module that's really "RBAC managing itself" (`roles`) got a single
  `roles.manage` permission instead — "when the user has manage it should
  have all access" — because nobody needs partial access to role
  management; only the trusted admin role will ever touch it. See
  `backend/database/seeders/PermissionSeeder.php`.
- **`syncPermissions()` replaces, it doesn't merge** — re-running the role
  seeder sets a role's permissions to exactly whatever `Permission::all()`
  returns *at that moment*. Editing the seeder file to remove a permission
  doesn't retroactively remove already-seeded rows from the database —
  that required an explicit one-time delete before re-seeding. See
  `backend/database/seeders/RoleSeeder.php`.
