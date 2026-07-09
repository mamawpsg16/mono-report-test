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
  **`openpyxl`** (xlsx, read-only streaming) + **`psycopg` (v3)**. Not
  pandas/psycopg2. `reader.py` picks CSV vs xlsx by **magic bytes**, not the
  extension — see "Known limitations" for the remaining caveats.
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

  → POST /api/customers/ask   { question }
       Laravel CustomerService::ask()
         → HTTP POST python-service /customers/ask
              embed(question) via fastembed (local, 384-dim)
              → pgvector similarity search (customer_embeddings <=> customers)  → reads Postgres
              → Groq chat completion, prompt = matched rows + question
         ← { answer, sources: [{customer_code, name, year}] }
  ← UI shows the answer + which customer rows it was based on
```

RAG note: `upsert_customers` (the confirm flow above) also computes and writes
each row's embedding into `customer_embeddings` in the same transaction, so a
customer row and its embedding never drift out of sync.

## Data model

`customers` (Laravel migration owns this):

| column | notes |
| --- | --- |
| `id` | pk |
| `original_filename` | source file the row came from |
| `customer_code` | part of natural key |
| `year` | unsigned smallint, part of natural key |
| `name` | required |
| `email` | required (NOT NULL — see migration `2026_07_09_024254`) |
| `phone` `address` `city` `country` | nullable |
| `is_active` | bool, default true; indexed |
| `created_by` `updated_by` | nullable FK → `users` |
| `timestamps` | |

- **Unique** `(customer_code, year)` — this is the natural key.
- **Diff rule** (`python-service/customers.py::compute_diff`): incoming rows are
  matched on `(customer_code, year)`. Existing pair whose compared fields differ =
  **update**; unseen pair = **new**. `upsert_customers` writes via
  `INSERT ... ON CONFLICT (customer_code, year) DO UPDATE`.

`customer_embeddings` (Laravel migration owns this; `pgvector` extension required —
`db` image is `pgvector/pgvector:pg16`):

| column | notes |
| --- | --- |
| `customer_id` | pk, FK → `customers.id`, cascade delete |
| `embedding` | `vector(384)` — fastembed `BAAI/bge-small-en-v1.5` output |
| `timestamps` | |

- HNSW index (`vector_cosine_ops`) for similarity search.
- Written by `python-service/customers.py::upsert_customers` in the same
  transaction as the customer row it belongs to.

## What's built

- **Compose stack**: `db` + `python-service` + `backend` + `frontend`, one
  `docker compose up --build`.
- **Auth**: Sanctum SPA session — `/login`, `/logout`, `auth:sanctum` guard; Vue
  router guard + `useAuth` composable + `LoginView`.
- **Upload (preview → confirm)**: `CustomerController` + `CustomerService` (Laravel),
  `python-service` `/customers/validate` + `/customers/process`, Vue
  `views/customers/components/FileUpload.vue` in an upload modal.
- **Customers list UI**: `views/customers/Index.vue` — searchable, paginated table,
  fixed columns (desktop, only when the table actually overflows) with responsive
  fallback on mobile. Shared components: `AppDataTable`, `AppPagination`,
  `AppSelect`, `AppSearchInput`, `AppModal`; composables `usePagination`,
  `useIsMobile`, `useColumnFreeze`.
- **RAG Q&A over customers** (`/customers/ask`): local `fastembed` embeddings +
  `pgvector` similarity search + Groq for generation, built by hand (no
  LangChain/LlamaIndex). Vue `views/customers/components/AskPanel.vue` in a
  modal, reached from the Customers toolbar. See the Architecture diagram above.

## Known limitations / upload roadmap

Record now, revisit when we harden the upload feature:

- **In-memory + row-by-row (still open).** `reader.py` loads the whole file into a
  list; `upsert_customers` loops row-by-row (one transaction, one commit per
  request). Fine for small files; revisit chunked reads + batched inserts / `COPY`
  before large uploads.
- ~~**xlsx zip-bomb guard.**~~ Resolved — `reader.py::assert_xlsx_safe` rejects
  `.xlsx` files with a suspicious compression ratio or decompressed size before
  openpyxl parses them. Row/cell-count capping remains open (`docs/backlog.md`).
- ~~**CSV only.**~~ Resolved — `read_xlsx` added; `read_customers_file` dispatches
  CSV vs xlsx by magic bytes (`PK\x03\x04`), not the extension.
- ~~**Debug output.**~~ Resolved — the per-row/header `print()`s are gone from
  `reader.py`.
- ~~**Port drift.**~~ Resolved — `docs/auth-sanctum-session.md` now uses the real
  ports (:8000 backend / :8001 python-service / :5173 frontend).

## Next

The AI-dev pivot is **underway**: the upload feature reached "solid enough"
(only backlog-level items remain — see above), and the RAG Q&A feature over
`customers` data has shipped (see "What's built"). The corpus ended up being
the app's own business data, not `docs/learning/journal.md` as originally
sketched here — the user explicitly wanted something built on real app data
with a real-world-realistic stack (pgvector, not a toy brute-force search),
partly because a **Flutter mobile client is planned next** and this feature's
API needs to already exist in a mobile-friendly shape. `journal.md` remains a
possible *future* corpus for a second RAG experiment, not dropped, just not
this one.

Mobile: **not started**. Requires Sanctum API-token issuance first (tracked in
`docs/backlog.md` — the app is 100% session/cookie auth today, which a Flutter
client can't use).

(The earlier draft's OCR/Tesseract input type is dropped — not pursuing it.)

## Verification (end to end)

`docker compose up --build` → open the Vue UI → **log in** → upload a small `.csv`
(3–5 rows) → preview shows new/update/error counts and tagged rows → **confirm** →
`SELECT * FROM customers;` shows the rows, list + search in the UI reflect them →
upload a malformed file → preview returns HTTP 422 with per-row errors, nothing
written.

**RAG (`/customers/ask`):** after the migration runs, `\d customer_embeddings`
shows a `vector(384)` column + HNSW index. After confirming an upload,
`SELECT customer_id, embedding IS NOT NULL FROM customer_embeddings;` shows a
row per uploaded customer. Click **Ask** in the toolbar, ask something the
sample data can answer (e.g. "which customers are in Accra?") → expect an
answer naming the right customer plus a sources list. Ask something
unrelated, or with an empty `customers` table → expect a graceful "I don't
know" / "no customer data yet", not a crash or hallucination. Remove
`GROQ_API_KEY` from `.env` and retry → expect a clean error, not a raw 500.
