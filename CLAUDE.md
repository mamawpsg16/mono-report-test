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
- Current milestone: **R0 close-out** — see PLAN.md "Roadmap: R0–R4" for the
  full phase plan (R1 RBAC → R2 identity/invitations → R3 rewards →
  R4 mobile, in that fixed order). R0 itself: commit RAG feature (done),
  pin deps (done), roadmap into PLAN.md (done), ADR for the RAG stack
  (open), delete `sample-data/bomb.xlsx` (open).
- Next after R0: **R1 — RBAC foundation** (spatie/laravel-permission).
- Default mode: GUIDE
- Open questions:
  - `chatgpt_plan.md` at repo root is a **superseded** earlier architecture
    exploration (shared-DB, `/api/imports/*`) — kept for reference, not the
    source of truth. PLAN.md is authoritative. Delete it? (ask before doing.)

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
