# dataforge

Learning project: started as a CSV/XLSX customer-data ingestion app, then
picked up local RAG Q&A over that data, then pivoted (2026-07-12) to a
**field-sales CRM** — reps visit customers, plan their week, chase
prospects. See `PLAN.md` for the full build history and decisions;
`CLAUDE.md` for the current session state.

Decoupled, Dockerized monorepo. Runs on any machine with Docker via one
`docker compose up`.

## Stack

| Part            | Tech                              | Port | Folder            |
|-----------------|------------------------------------|------|--------------------|
| Backend API     | Laravel 10, API-only (PHP 8.2)     | 8000 | `backend/`         |
| Frontend        | Vue 3 + Vite SPA                   | 5173 | `frontend/`        |
| Data service    | FastAPI (`python-service`)         | 8001 | `python-service/`  |
| Database        | PostgreSQL 16 + pgvector           | 5432 | (Docker volume)    |
| Dev mail catcher| Mailpit                            | 8025 | (Docker image)     |

- **Backend ↔ python-service is synchronous HTTP**, not a job queue — Laravel
  calls `python-service` over the compose network and waits for a response.
  There is no poll worker and no `imports` job table (an earlier design
  draft used that pattern; it was abandoned before anything shipped).
- **Auth** is Laravel Sanctum **SPA session** (cookie + CSRF), not bearer
  tokens — see `docs/auth-sanctum-session.md`.
- **Laravel owns the schema** (migrations); `python-service` parses,
  validates, diffs, and writes `customers` rows directly to Postgres over a
  shared connection.

## How the pieces talk (customer upload, the original core flow)

```
Browser (Vue :5173)
  → POST /api/customers/preview   (multipart file, Sanctum session cookie)
       Laravel stores the file, then calls python-service to validate/diff it
         → HTTP POST python-service :8001 /customers/validate  ──►  reads Postgres
  ← preview: new / update / error counts + tagged rows

  → POST /api/customers/confirm
       Laravel calls python-service to upsert
         → HTTP POST python-service /customers/process  ──►  writes Postgres
  ← customers list refreshes
```

The CRM screens (Prospects, Visits, Weekly Plan, Coverage Report) are plain
Laravel REST endpoints under `auth:sanctum` + `permission:*` middleware —
no python-service involvement; python-service only exists for file
parsing/validation/diffing and the RAG embedding pipeline.

## Quick start

```bash
git clone <this repo>
cd mono-report-test
cp .env.example .env
docker compose up --build
```

Then:

- Frontend → <http://localhost:5173>
- Backend API → <http://localhost:8000>
- python-service (Swagger docs) → <http://localhost:8001/docs>
- Mailpit (dev mail catcher — invitation/set-password emails land here,
  nothing leaves the machine) → <http://localhost:8025>

First run needs the database migrated and seeded (roles/permissions, an
admin login):

```bash
docker compose exec backend php artisan migrate --seed
```

Default seeded login (see `backend/database/seeders/` for the exact seeder):
`admin@dataforge.test` / `password`.

### `.env` — what you actually need to fill in

Most of `.env.example` works unmodified for local dev. The one required
value: `GROQ_API_KEY` (from console.groq.com/keys), needed only if you want
the **Ask** (RAG Q&A) feature on the Customers page to work — the rest of
the app runs fine without it.

## Folders

```
mono-report-test/
├── backend/           Laravel 10 API — migrations, controllers, services, policies
├── frontend/          Vue 3 + Vite SPA
├── python-service/    FastAPI — file parsing/validation/diffing, RAG embeddings
├── docs/
│   ├── adr/           decision records
│   ├── security/      one file per security topic
│   ├── learning/      journal.md — concepts learned, in the user's own words
│   └── backlog.md     parked ideas from reviews and YAGNI calls
├── .claude/rules/      mentor-mode rules this project is built under (see MENTOR.md)
├── docker-compose.yml
├── .env.example
├── PLAN.md            full build history, architecture, and roadmap
└── CLAUDE.md          current session state (what's done, what's next)
```

## Running tests

```bash
docker compose exec backend php artisan test
```

python-service has no automated test suite yet (tracked in
`docs/backlog.md`) — its behavior is verified manually per PLAN.md's
verification checklist.

## Where to look next

- `PLAN.md` — architecture diagrams, data model, full roadmap (what's built,
  what's next, every phase's decisions).
- `CLAUDE.md` — the current session's state: what was just finished, what's
  open, default working mode.
- `docs/adr/` — why things were built the way they were.
- `MENTOR.md` — this project is built as a mentored learning exercise; that
  file explains the working style if you're picking this repo up cold.
