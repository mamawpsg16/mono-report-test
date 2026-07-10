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

## R1 milestone 3 — guarding `/api/customers*` with permission middleware

- **Middleware aliases** — a short name (`permission`) a route can reference
  instead of writing out the full middleware class path. Spatie's
  `PermissionMiddleware`/`RoleMiddleware` aren't auto-registered by the
  package in this Laravel version — they had to be added to
  `$middlewareAliases` by hand. See `backend/app/Http/Kernel.php`.
- **`permission:` middleware: `|` means OR, stacking means AND** — one
  middleware entry like `permission:customers.create|customers.update`
  means "has *either*." Requiring *both* (needed here because a single
  upload can create and update rows in the same request) meant applying
  two separate `permission:` middleware entries, not one entry with two
  names in it. See `backend/routes/api.php`.
- **Duplicate route definitions fail silently, not loudly** — Laravel
  matches the *first* registered route for a given path+method. A
  "corrected" duplicate registered afterward isn't an error, a warning, or
  even reachable — it's just dead code, which is exactly what makes this
  bug dangerous for anything security-related: the guarded version sits
  right there in the source looking correct while never actually running.
  Caught before it shipped by checking the diff before applying it.
- **Why an unhandled `UnauthorizedException` still returns clean JSON** —
  it extends Symfony's `HttpException`, which Laravel's default exception
  handler already knows how to render as a proper status-coded JSON error
  for API-expecting requests — no custom exception handling needed, same
  mechanism that already turns validation failures into JSON 422s.

## R1 milestone 4 — users admin (role assignment) + confirm/toast infra

> Draft definitions — reword in your own words when you do the understanding
> review; the point of the journal is *your* phrasing, not mine.

- **The last-admin invariant** — an authorization rule enforced as "the count
  of `roles.manage` holders must never reach zero," checked before
  `syncRoles`. Phrased against the *permission*, not the `admin` *role*, so a
  future admin-capable role still counts — same golden rule as everywhere else
  (check permissions, not roles). It collapses "don't remove yourself" and
  "don't remove the last admin" into one condition. See
  `backend/app/Http/Controllers/Admin/UserController.php` and
  `docs/security/04-privilege-management.md`.
- **Self-referential unique on edit** — a `unique:users,email` rule would
  reject a user for "already having" their own email when you save an unchanged
  form. `Rule::unique('users','email')->ignore($user->id)` excludes their own
  row from the check. Classic edit-form gotcha. See `UpdateUserRequest.php`.
- **List pagination is Laravel, never Python** — adding pagination to the users
  list was `->get()` → `->paginate()`, hitting Postgres directly via Eloquent.
  `python-service` only ever handles file parsing + RAG, never ordinary reads —
  so the customers *and* users lists paginate the same way, Python-free. See
  `UserController::index` and `CustomerService::listPaginated`.
- **Module-scoped composable = a singleton service** — `useConfirm`/`useToast`
  keep state at *module* scope (created once, shared by every importer), and a
  single host component (`<ConfirmModal>`/`<ToastHost>` in `App.vue`) renders
  it. Any component calls `confirm()`/`toast.*` like a function without mounting
  UI; the "TV in the always-on room, remote everywhere" split. One host only —
  a second would double-bind the same state. See `frontend/src/composables/`.
- **Promise + async `onConfirm` for in-dialog loading** — `confirm()` returns a
  promise that resolves on accept/dismiss; passing an async `onConfirm` makes
  the dialog own the loading spinner and keep itself open to show a thrown
  server error (e.g. the last-admin 422). That's how "loading while saving"
  lives on the dialog, not the page button.
