# dataforge — build plan & decisions

> Handoff doc. Open this project in VSCode and continue from here. This captures every
> decision made so far so a fresh Claude Code session (or you) can pick up cold.

## What this is

A **multi-type data-ingestion app**, built as a learning project. User is learning by
doing — **explain concepts, keep teaching comments in code, go one phase at a time and
STOP for review** before the next. User asks lots of "why" — answer before writing code.

Core flow: user uploads a file in the Vue UI → Laravel stores it + records a job →
a **separate Python worker** processes it → results land in Postgres → UI shows status.

**CSV import is the first feature** (real use case: customer CSVs, up to ~100k rows).
Later input types (OCR on uploaded images via Tesseract, etc.) reuse the same worker
pattern — designed for it now, **not built yet**.

## Locked decisions

- **Decoupled, Dockerized monorepo.** Runs on any machine via `docker compose up`.
- **Backend:** Laravel 10 (chosen over 11 because host PHP is 8.1; Docker image uses PHP 8.2).
  **API-only** — does not render HTML.
- **Frontend:** Vue 3 + Vite **standalone SPA** (separate from backend, not Inertia).
- **Importer:** **plain Python** (no Django/FastAPI) — a **worker** in its own container.
- **Database:** **PostgreSQL 16**, shared by Laravel and Python.
- **Integration = shared DB, not subprocess.** Docker containers can't subprocess each
  other, so Laravel writes a job row; the Python worker **polls** Postgres for pending
  jobs, processes, writes results, updates status. Classic worker/job-queue pattern.
- **DB writes:** Python writes result rows **directly**. Laravel owns the **schema**
  (migrations) = single source of truth for structure.
- **Parse libs:** **pandas** (csv + xlsx, one API) + **openpyxl** (pandas's xlsx engine —
  this is the "pyfile" lib the user's workmate uses). `psycopg2` to reach Postgres.
  > CONFIRM: user picked "Other" for parse lib once — verify pandas is fine, or swap to
  > the exact lib their workmate named.
- **Auth:** **login coming in a later phase** (Laravel Sanctum). Early phases are open;
  add auth + ownership checks before any real deploy. See `docs/security/03-...md`.

## Scale note (drives Phase 4 design)

CSVs can be ~100k rows. So the worker must:
- **chunked read** — `pd.read_csv(path, chunksize=5000)`, never slurp all rows into RAM;
- **bulk insert** — Postgres `COPY` (psycopg2 `copy_expert`) or batched inserts, not
  row-by-row;
- **progress** — update `imports.processed_rows` per chunk so the UI shows a live count;
- **per-row errors** — collect bad rows, don't let one kill the job.

## Architecture

```
Browser (Vue SPA :5173)
  → POST /api/imports         Laravel: validate + store file, INSERT imports(status=pending)
                              ─────────────────────────────────────────────►  Postgres :5432
Python importer (own container)
  → poll Postgres for status=pending
  → pandas chunked read → validate → COPY rows in → update status=done/failed + counts
Browser (Vue)
  → poll GET /api/imports/{id} until done/failed
```

## Build phases (one at a time, STOP for review between each)

- **Phase 0 — scaffold + Docker.** `composer create-project laravel/laravel:^10 backend`;
  `npm create vite@latest frontend -- --template vue`; Dockerfiles for backend/frontend/
  importer; `docker compose up` boots Postgres + all three. Verify each service starts.
- **Phase 1 — DB schema (migrations).** `imports` table: id, type ('csv' now, room for
  'ocr_image' later), original_filename, stored_path, status (pending/processing/done/
  failed), total_rows, processed_rows, error_message, timestamps. Target table, e.g.
  `customers`. Teach: why Laravel owns schema even though Python writes.
- **Phase 2 — upload endpoint (Laravel).** `POST /api/imports`: validate (extension +
  real MIME + size), store to `storage/app/imports` (random name, outside webroot),
  INSERT imports row, return id. `GET /api/imports/{id}` for status. See `docs/security/02`.
- **Phase 3 — Vue upload UI.** File input → axios `FormData` POST → poll status every ~2s.
  Teach: FormData vs JSON, why polling (async worker).
- **Phase 4 — Python worker (core lesson).** `importer/worker.py` poll loop + `parse_csv.py`:
  pandas chunked read, validate, `COPY` into Postgres, update counts/status. Plain Python.
  `requirements.txt`: pandas, openpyxl, psycopg2-binary. Run as **non-root** in container.
- **Phase 5 — wire importer ↔ Postgres end to end.** Confirm Laravel-written job is picked
  up, processed, status flips, rows land. Handle failures (mark failed + store error).
- **Phase 6 — polish.** Per-row error report to UI; edge cases (empty file, wrong columns,
  duplicates, zip-bomb/oversize guard); delete/quarantine source file after import (PII).

## Later (designed for, not built)

- **OCR input type:** images → `pytesseract` + `Pillow`, Tesseract binary in importer image.
  Same `imports` row with `type='ocr_image'`; worker dispatches by type. Security:
  decompression bombs, Pillow CVEs, MIME spoofing — see `docs/security/02`.
- **Auth/login:** Sanctum + ownership checks (`docs/security/03`).

## Security docs (one file per topic, in `docs/security/`)

Done: `00-overview`, `01-owasp-top-10`, `02-file-upload-security`.
TODO: `03-authentication-authorization` (login phase), `04-database-security`,
`05-docker-security`, `06-secrets-management`, `07-dependency-security`.

## Tooling on host (checked 2026-06-27)

PHP 8.1.10 · Composer 2.5.8 · Node 20.17 · npm 9.9 · Python 3.11.5 · MySQL not installed.
(Docker makes host versions mostly irrelevant — images pin their own.)

## Verification (end to end, after Phase 5)

`docker compose up --build` → open Vue UI → upload a small `.csv` (3–5 rows) → watch
status pending→processing→done → `SELECT * FROM customers;` shows rows, counts match →
upload a malformed file → status=failed with error shown.
