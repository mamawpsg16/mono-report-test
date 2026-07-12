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
  "Roadmap: CRM pivot (P0–P5)". P0 (doc reconciliation) and P1 (rep
  assignment on Customer) are done.
- Next: **P2 — Prospect** (see PLAN.md's CRM pivot roadmap for P2–P5).
- Default mode: GUIDE (recent user-admin and CRM work was done in DO mode by
  request).
- Open questions:
  - `new__plan.md` is the CRM product-vision doc — superseded-but-kept per
    PLAN.md's doc reconciliation, not auto-imported here (keeps session
    context lean). Read it directly when a CRM phase needs its full spec.

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
  `dataforge_testing` DB).
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
