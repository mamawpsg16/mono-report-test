# CLAUDE.md — dataforge

Learning project. I am upskilling: the rules below make you my mentor,
not my code generator. They override any instinct to "just build it".

## Mentor rules (always apply)

@.claude/rules/00-mentor-role.md
@.claude/rules/01-teaching-method.md
@.claude/rules/02-workflow.md
@.claude/rules/03-code-review.md
@.claude/rules/04-security.md
@.claude/rules/05-documentation.md
@.claude/rules/06-testing.md
@.claude/rules/07-git.md

Note: if the rule files live in `.claude/rules/`, Claude Code loads them
automatically as project memory — the explicit @imports above are a
belt-and-braces index so this file documents what applies. If you keep
the kit elsewhere (e.g. `~/mentor-kit/`), the @imports are what load them.

## Project plan (what we're building, decisions, phases)

@PLAN.md

## Current state — UPDATE THIS as we go

- Built: Docker Compose stack (db + python-service + backend + frontend),
  Sanctum SPA session auth (login/logout, guarded routes, Vue router
  guard), the customer upload flow (preview → confirm) end to end, and the
  Customers list UI (searchable/paginated table, fixed columns, responsive
  mobile behavior).
- The AI-dev pivot happened: RAG Q&A over `customers` data shipped
  (fastembed + pgvector + Groq, hand-built). Upload feature is stable —
  remaining upload items (in-memory + row-by-row upsert) are backlog-level,
  not blocking.
- **R1 (RBAC) is DONE**: users admin (list/edit/roles + create-by-invite,
  deactivate, resend) and roles admin (create/rename + permissions grid), all
  gated by `roles.manage` with last-admin guards. See PLAN.md "What's built".
- **R2 (identity/invitations) partly done**: the admin-user invite + mail
  machinery shipped (Mailpit dev service, `invitations` password broker,
  `UserInvitation` mail, public `/set-password`). **Remaining R2**: the
  *customer* side — `customer_accounts`, auto-invite on upload, and mobile
  bearer-token login (`POST /api/mobile/login`). Mail is synchronous (no queue).
- **CRM pivot (2026-07-12)**: direction changed from the rewards/points
  roadmap (R3/R4, now superseded) to a **field-sales CRM** — see PLAN.md
  "Roadmap: CRM pivot (P0–P5)". P0–P3 are **done**: P0 (doc reconciliation),
  P1 (rep assignment on Customer), **P2 (Prospect)**, **P3 (Visit)**.
- **Web-vs-mobile split (decided 2026-07-13, see PLAN.md's CRM roadmap
  intro)**: web = view · report · plan · admin; mobile (Flutter, not started)
  = field execution. P2/P3 shipped **backend full CRUD API + web view-only**;
  creating/editing prospects and starting/finishing visits are mobile-only
  actions (API is ready, waiting on the mobile app). **P4 breaks this
  pattern on purpose** — per `new__plan.md`'s "Weekly Coverage Plan" section,
  planning is a **web** feature, so P4's web screen gets real create/manage,
  not just viewing.
- **Next: P4 — VisitPlan / VisitPlanEntry — IN PROGRESS, schema-only,
  picks up here:**
  - Done: 3 migrations committed (`2026_07_14_000001_create_visit_plans_table`,
    `..._000002_create_visit_plan_entries_table`,
    `..._000003_add_visit_plan_entry_id_to_visits_table`) — `visit_plans`
    (representative_id + week_start_date, unique per rep/week),
    `visit_plan_entries` (visit_plan_id + customer_id + planned_date, unique
    triple, day-only per the vision doc), and the `visits.visit_plan_entry_id`
    FK deferred from P3 (now added since its target table exists — this
    codebase never leaves a column FK-less).
  - **NOT YET DONE, first steps next session**: (1) run
    `docker compose exec backend php artisan migrate` and verify the 3 new
    tables/columns with `\d visit_plans` / `\d visit_plan_entries` / `\d visits`
    — **these migrations were never run or verified this session**; (2)
    `VisitPlan`/`VisitPlanEntry` models (uuid + `HasPublicUuid`, `scopeVisibleTo`
    mirroring `Prospect`); (3) service/controller/policy/routes — likely a
    "get-or-create this week's plan" endpoint + add/remove entry endpoints;
    (4) auto-link logic in `VisitService::start()` — when a visit starts and
    matches a planned entry for that customer/day, set
    `Visit.visit_plan_entry_id` automatically (**decided: this is automatic,
    mobile-side only — no manual "tick" UI on web or mobile**); (5) the web
    "My Week" planning screen (real add/remove, not view-only — see the split
    note above) + `VisitPlanSeeder` demo data; (6) reconcile `PLAN.md`'s P4
    row + this section once done, same as P2/P3.
  - Mode: **DO** (per request, matches P2/P3's rhythm — build, stop at
    checkpoints for verification).
- Default mode: GUIDE (recent user-admin and CRM work was done in DO mode by
  request).
- Open questions:
  - `new__plan.md` is the CRM product-vision doc — superseded-but-kept per
    PLAN.md's doc reconciliation, not auto-imported here (keeps session
    context lean). Read it directly when a CRM phase needs its full spec —
    its short "Weekly Coverage Plan" section (day → customer list, no
    approvals in MVP) is P4's actual spec.
  - `README.md` is noticeably stale (still describes the original poll-worker/
    pandas architecture PLAN.md's own banner says was abandoned). Not fixed
    yet — flagged during a P4 handoff-note update, not in scope for that
    moment. Worth a dedicated pass.

## Conventions — always follow the stack's best practice

Default to each stack's idiomatic best practice, not ad-hoc shortcuts. The
established conventions in this codebase (follow these; extend the list when
a new one is settled):

- **Laravel**: validation lives in **Form Requests** (`app/Http/Requests`),
  never inline `$request->validate()` in controllers; shared/complex checks
  become custom Rules (`app/Rules`, e.g. `SalesRepresentative`). Controllers
  stay thin — business logic goes in services (`CustomerService` pattern).
  Route-level `permission:*` middleware does the gating; `roles.manage` is
  the admin gate. Route model binding by `uuid`, never sequential ids
  (`HasPublicUuid`). Feature tests follow `CustomerScopeTest`'s shape
  (RefreshDatabase + seeders + plain `Model::create()` helpers, isolated
  `dataforge_testing` DB). **Soft delete for CRM business records** (decided
  2026-07-14, P4): anything a rep creates/manages — `Prospect`, `Visit`,
  `VisitPlan`/`VisitPlanEntry`, future `Task`s — gets `SoftDeletes` +
  `deleted_by` (who removed it, for audit), not a real `DELETE`; a plain
  `UNIQUE` on such a table needs a **partial index** (`WHERE deleted_at IS
  NULL`) instead, or a soft-deleted row will wrongly block re-adding the same
  data (see `visit_plan_entries_unique_live`). System/infra tables (`users`,
  `roles`, `permissions`, `sessions`) stay hard-delete — not business records.
  `VisitPlanEntry` has this now; `Prospect` still hard-deletes and needs
  retrofitting (`docs/backlog.md`).
- **Vue**: reuse the shared components/composables (`DatatableServer`,
  `AppModal`, `useConfirm`/`useToast`, `useAuth().can()`); buttons come from
  the **global** `.btn-*` classes in `App.vue`, never redefined per view.
  Edit-in-place modals follow `UsersView.vue`'s pattern (row prop, confirm()
  around the save, toast after, patch the list in place instead of
  refetching where possible). Nav/routes/UI actions are permission-gated
  with `auth.can()`.
- **Python service**: stdlib-first (csv/openpyxl/psycopg), validate at the
  boundary, reject loudly rather than clean silently, batch DB writes
  (`unnest`/`executemany`), keep row + embedding writes in one transaction.

## Project-specific facts

- Stack: Laravel 10 (API-only, PHP 8.2 in Docker) · Vue 3 + Vite SPA ·
  **FastAPI python-service** · PostgreSQL 16 · Docker Compose.
- Integration is **synchronous HTTP, not shared-DB polling**: Laravel
  `CustomerService` calls `python-service` over the compose network
  (`PYTHON_SERVICE_URL=http://python-service:8001`, via
  `config('services.python_service.url')`) — `POST /customers/validate`
  (preview/diff) and `POST /customers/process` (upsert). There is no
  `imports` job table and no polling; the old Import model/controller/
  service/migration were deleted.
- Laravel owns the schema (migrations); `python-service` parses, validates,
  diffs, and **writes customer rows directly** to Postgres.
- Parse/DB libs in python-service: stdlib **`csv`** + **`openpyxl`** (xlsx,
  read-only) + **`psycopg` (v3)** — not pandas/psycopg2. `reader.py` dispatches
  CSV vs xlsx by real **magic bytes**, not the extension. (See PLAN.md
  limitations for the remaining at-scale caveat.)
- Auth: **Sanctum SPA session** (cookie + CSRF, not tokens), fully wired.
  See `docs/auth-sanctum-session.md`.
- Frontend: `frontend/src/views/customers/` is the working feature area;
  shared UI in `frontend/src/components/` (AppDataTable, AppPagination,
  AppSelect, AppSearchInput, AppModal); composables in
  `frontend/src/composables/` (useAuth, usePagination, useIsMobile).
- Run: `docker compose up --build` · verify per PLAN.md checklist.
