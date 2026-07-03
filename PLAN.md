# dataforge — build plan & decisions

> Handoff doc. Open this project in VSCode and continue from here. Captures the
> real, as-built architecture so a fresh Claude Code session (or you) can pick up
> cold. Rewritten 2026-07-02 to match what actually shipped (the earlier draft
> described a poll-worker design that was abandoned).

## What this is

A **data-ingestion app**, built as a learning project. User is learning by doing —
**explain concepts, keep teaching comments in code, go one phase at a time and STOP
for review** before the next. User asks lots of "why" — answer before writing code.

Core flow: user logs in → uploads a customer CSV in the Vue UI → Laravel stores it
and asks the Python service to **validate + diff** it → UI shows a preview
(new / update / error rows) → user confirms → Python **upserts** the rows into
Postgres → UI lists them.

**CSV customer import is the built feature.** See "Next" for where this goes.

## Locked decisions

- **Decoupled, Dockerized monorepo.** Runs via `docker compose up`. Services:
  `db` (Postgres), `python-service` (FastAPI), `backend` (Laravel), `frontend` (Vue).
- **Backend:** Laravel 10 (host PHP is 8.1; Docker image uses PHP 8.2). **API-only** —
  does not render HTML.
- **Frontend:** Vue 3 + Vite **standalone SPA** (separate from backend, not Inertia).
  Vue Router with an `AppLayout` (auth-guarded) and a `LoginView`.
- **Data service:** **FastAPI `python-service`** (not a plain-Python worker). Does all
  file parsing, validation, diffing, and DB writes.
- **Database:** **PostgreSQL 16**, shared. Laravel owns the **schema** (migrations) =
  single source of truth for structure; Python writes customer rows directly.
- **Integration = synchronous HTTP, not a job queue.** Docker containers can't
  subprocess each other, so Laravel calls the Python service over the compose network
  (`PYTHON_SERVICE_URL=http://python-service:8001`, read via
  `config('services.python_service.url')`). Request/response, no `imports` job table,
  no polling, no async status.
- **Parse/DB libs:** Python stdlib **`csv`** (utf-8-sig, strips Excel BOM) +
  **`psycopg` (v3)**. Not pandas/openpyxl/psycopg2 — see "Known limitations".
- **Auth: DONE.** Laravel **Sanctum SPA session** auth (cookie + CSRF, not tokens).
  All `/api/customers*` routes sit behind `auth:sanctum`. Details in
  `docs/auth-sanctum-session.md`.

## Architecture

```
Browser (Vue SPA :5173)
  → POST /api/customers/preview   (multipart file, Sanctum session cookie)
       Laravel CustomerService: store file to storage/app/uploads/<uuid>.<ext>
         → HTTP POST python-service /customers/validate  ──────────►  python-service :8001
              read CSV → validate rows → diff vs existing customers      → reads Postgres :5432
         ← { summary, rows, errors }   (on errors: delete file, HTTP 422)
  ← preview shown in UI (new / update / error counts + tagged rows)

  → POST /api/customers/confirm   { stored_path, original_filename }
       Laravel CustomerService
         → HTTP POST python-service /customers/process
              read CSV → validate → upsert rows (INSERT ... ON CONFLICT)  → writes Postgres
         ← { status, processed_rows, errors }
       Laravel deletes the stored file afterward
  ← UI refreshes the customers list

  → GET /api/customers?page&per_page&search
       Laravel: paginate customers (ilike search across fields) + creator/updater
```

## Data model

`customers` (Laravel migration owns this):

| column | notes |
| --- | --- |
| `id` | pk |
| `original_filename` | source file the row came from |
| `customer_code` | part of natural key |
| `year` | unsigned smallint, part of natural key |
| `name` | required |
| `email` `phone` `address` `city` `country` | nullable |
| `is_active` | bool, default true; indexed |
| `created_by` `updated_by` | nullable FK → `users` |
| `timestamps` | |

- **Unique** `(customer_code, year)` — this is the natural key.
- **Diff rule** (`python-service/customers.py::compute_diff`): incoming rows are
  matched on `(customer_code, year)`. Existing pair whose compared fields differ =
  **update**; unseen pair = **new**. `upsert_customers` writes via
  `INSERT ... ON CONFLICT (customer_code, year) DO UPDATE`.

## What's built

- **Compose stack**: `db` + `python-service` + `backend` + `frontend`, one
  `docker compose up --build`.
- **Auth**: Sanctum SPA session — `/login`, `/logout`, `auth:sanctum` guard; Vue
  router guard + `useAuth` composable + `LoginView`.
- **Upload (preview → confirm)**: `CustomerController` + `CustomerService` (Laravel),
  `python-service` `/customers/validate` + `/customers/process`, Vue
  `views/customers/components/FileUpload.vue` in an upload modal.
- **Customers list UI**: `views/customers/Index.vue` — searchable, paginated table,
  fixed columns (desktop) with responsive fallback on mobile. Shared components:
  `AppDataTable`, `AppPagination`, `AppSelect`, `AppSearchInput`, `AppModal`;
  composables `usePagination`, `useIsMobile`.

## Known limitations / upload roadmap

Record now, revisit when we harden the upload feature:

- **In-memory + row-by-row.** `reader.py` loads the whole CSV into a list;
  `upsert_customers` loops row-by-row with one commit per request. Fine for small
  files; revisit chunked reads + batched inserts / `COPY` before large uploads.
- **CSV only.** `reader.py` implements `read_csv` only, but Laravel's
  `CustomerService` accepts `xlsx` too — an `.xlsx` upload would reach the service
  and fail. Either add xlsx parsing or tighten the Laravel allow-list.
- **Debug output.** `reader.py` `print()`s every row/header — remove before scale.
- **Port drift.** `docs/auth-sanctum-session.md` references :8009/:8010; compose uses
  :8000 (backend) / :8001 (python-service) / :5173 (frontend). Reconcile the doc.

## Next (after the upload feature is solid)

Finish/polish uploads first (limitations above). Then the learning track pivots to
**AI development** — the user's goal is to become an **AI developer** (RAG / LLM app
work). `docs/learning/journal.md` accumulates concepts in the user's own words as we
go; that log is a natural first corpus to build a RAG experiment on top of.

(The earlier draft's OCR/Tesseract input type is dropped — not pursuing it.)

## Verification (end to end)

`docker compose up --build` → open the Vue UI → **log in** → upload a small `.csv`
(3–5 rows) → preview shows new/update/error counts and tagged rows → **confirm** →
`SELECT * FROM customers;` shows the rows, list + search in the UI reflect them →
upload a malformed file → preview returns HTTP 422 with per-row errors, nothing
written.
