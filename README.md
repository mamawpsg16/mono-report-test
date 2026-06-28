# dataforge

Learning project: a **multi-type data-ingestion app** — upload files (CSV/XLSX now;
images for OCR later) → a Python worker processes them → results saved to the database.

Decoupled, Dockerized, runs on any machine with Docker. **Built CSV-first, one phase at a
time.** OCR and other input types slot into the same worker pattern later.

## Stack

| Part        | Tech                          | Port | Folder      |
|-------------|-------------------------------|------|-------------|
| Backend API | Laravel 10 (PHP 8.2 in Docker)| 8000 | `backend/`  |
| Frontend    | Vue 3 + Vite SPA              | 5173 | `frontend/` |
| Importer    | Plain Python (pandas worker)  | —    | `importer/` |
| Database    | PostgreSQL 16                 | 5432 | (volume)    |

## How it works (the worker pattern)

```
Browser (Vue)
  → POST /api/imports          Laravel stores file + inserts imports row (status=pending)
                               ──────────────────────────────────────────────►  Postgres
Python importer (separate container)
  → polls Postgres for status=pending
  → reads file with pandas, validates rows
  → INSERTs rows, updates imports.status = done / failed
Browser (Vue)
  → polls GET /api/imports/{id} until done/failed
```

Why a separate worker instead of Laravel running Python directly? In Docker each service is
its own container — one container can't run a subprocess inside another. So the two talk
through the **shared database**: Laravel writes a job, Python picks it up. That's the
classic **worker / job-queue** pattern.

## Folders

```
dataforge/
├── backend/        Laravel 10 API (scaffolded in Phase 0)
├── frontend/       Vue 3 + Vite SPA (scaffolded in Phase 0)
├── importer/       Python parser + worker loop
├── docs/security/  OWASP + security best-practice notes (one file per topic)
├── docker-compose.yml
├── .env.example
└── PLAN.md         full build roadmap (phases)
```

## Quick start (after Phase 0 scaffolding)

```bash
cp .env.example .env
docker compose up --build
# backend  → http://localhost:8000
# frontend → http://localhost:5173
```

## Status

- [ ] Phase 0 — scaffold Laravel + Vue + Docker Compose + Postgres
- [ ] Phase 1 — DB schema (migrations)
- [ ] Phase 2 — upload endpoint
- [ ] Phase 3 — Vue upload UI + status polling
- [ ] Phase 4 — Python parser + worker loop
- [ ] Phase 5 — wire importer to shared Postgres
- [ ] Phase 6 — polish + per-row error reporting

See `PLAN.md` for detail. Built one phase at a time.
