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
  "Roadmap: CRM pivot (P0–P5)". P0–P4 are **done**: P0 (doc reconciliation),
  P1 (rep assignment on Customer), **P2 (Prospect)**, **P3 (Visit)**,
  **P4 (VisitPlan/VisitPlanEntry)**.
- **Web-vs-mobile split (decided 2026-07-13, see PLAN.md's CRM roadmap
  intro)**: web = view · report · plan · admin; mobile (Flutter, not started)
  = field execution. P2/P3 shipped **backend full CRUD API + web view-only**;
  creating/editing prospects and starting/finishing visits are mobile-only
  actions (API is ready, waiting on the mobile app). **P4 broke this
  pattern on purpose** — per `new__plan.md`'s "Weekly Coverage Plan" section,
  planning is a **web** feature, so P4's web screen got real create/manage,
  not just viewing.
- **P4 — VisitPlan / VisitPlanEntry — DONE (2026-07-14).** Shipped, in
  order: the 3 migrations (`visit_plans`, `visit_plan_entries`,
  `visits.visit_plan_entry_id`); `VisitPlan`/`VisitPlanEntry` models
  (uuid + `HasPublicUuid`, `scopeVisibleTo` mirroring `Prospect`);
  `VisitPlanController`/`VisitPlanService` (get-or-create this week's plan +
  add/remove-entry endpoints) + `VisitPlanEntryPolicy`; the web "My Week"
  planning screen (`views/plans/`, real add/remove, a server-searched
  customer picker — see ADR-worthy commit "Search the visit-plan customer
  picker server-side"); the visit→plan **auto-link** in
  `VisitService::start()` (rep + customer + today's date match, app-level
  dedup against double-claiming — ADR 0005); and the **plan-entry freeze**
  (`planned_date <= today` is immutable server-side in
  `VisitPlanService`/`VisitPlanEntryPolicy`, not just a disabled button, so
  the future planned-vs-actual report can't be gamed). `VisitPlanEntry` is
  this codebase's first soft-deleted business record, which became the
  standing CRM convention (see Conventions below). PLAN.md's P4 row
  reconciled to match this.
- **Planned-vs-actual coverage report — DONE (2026-07-15).** The payoff P4's
  auto-link and freeze existed for. `CoverageReportService` derives status
  (visited/missed/pending) on read from `VisitPlanEntry`'s `visit` relation
  and `planned_date` — no stored/denormalized status column, since "missed"
  has no write event to trigger keeping one in sync without a scheduler this
  project doesn't have. Two endpoints split by permission, not by a
  client-supplied id (`GET /api/reports/coverage/my-week`, `visits.view`,
  identity always from `$request->user()`; `/team`, `roles.manage`, every
  rep) — closes the IDOR risk where a rep could otherwise pass another rep's
  id and see their week. Web screen at `/coverage-report` (admin/rep branch,
  same pattern as `dashboard/Index.vue`). 6 feature tests
  (`CoverageReportTest`), including one that proves a spoofed
  `representative_id` query param is silently ignored, not just rejected.
  **Close-out DONE (2026-07-20)**: code review, understanding review, and
  the write-up all happened — ADR 0006 (IDOR endpoint split) + ADR 0007
  (computed-vs-stored status) + a journal section. The review surfaced three
  parked items in `docs/backlog.md` ("From coverage-report code review"): an
  open visit (`ended_at IS NULL`) counting as "visited" — a real gaming
  vector to decide on — plus the missing `pending_count` and an unbounded
  `week_start`. **Still open**: no `VisitPlanSeeder` demo data.
- **Next**: no CRM phase is actively in progress. The coverage-report
  close-out is done (2026-07-20); `Prospect` soft-delete retrofit done
  (2026-07-21). Candidates: the open-visit "visited" gaming-vector fix
  (`docs/backlog.md`, from the code review — smallest, and it hardens the
  anti-gaming report), `VisitPlanSeeder`, or P5 (`Customer::scopeVisibleTo`
  coverage-simplification cleanup). Not yet chosen — pick one at the start
  of the next session.
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
  `VisitPlanEntry` and `Prospect` both have this now (Prospect retrofitted
  2026-07-21); `Visit`, `VisitPlan`, and future `Task`s still hard-delete
  (no user-facing delete path exercises them yet — retrofit when one does).
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
