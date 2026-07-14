# Backlog

Parked ideas from reviews and YAGNI calls. One line each: what, why parked.

## From Claude Design mock (DataForge.dc.html, 2026-07-02)

- **Stat cards** (Total Customers / Cities / Latest Year) on Customers page —
  purely decorative, nothing depends on them. Revisit if there's an actual
  reporting need.
- **City filter chips** above the table — search box already covers city
  via `ilike` across fields. Revisit if search proves too slow/broad at
  real data volume.
- **Left sidebar nav** (Records / Reports / Segments / Tags / History /
  Integrations) — all dead links, nothing built behind them. Revisit only
  when those sections actually exist; don't build nav to nothing.
- **Export button** — no export functionality built. Revisit if a real
  export need shows up.
- **Bulk-select checkboxes** on table rows — no bulk action exists yet
  (nothing to select rows *for*). Revisit alongside whatever bulk action
  is actually needed (bulk delete, bulk tag, etc).

## From code review, 2026-07-03

- **`AppSelect` uses ARIA `role="listbox"`/`role="option"` on plain divs**
  instead of native `<select>`/`<option>`. Linter (Web:S6819) flags this —
  real accessibility gap (inconsistent screen-reader behavior vs a native
  control). Pre-existing, not introduced by the dropdown-clipping fix.
  Revisit if accessibility becomes a real requirement, or when AppSelect
  next gets touched for another reason.

## From upload roadmap, 2026-07-09

- ~~**Harden xlsx uploads against zip-bomb decompression.**~~ Resolved
  2026-07-09 — `reader.py::assert_xlsx_safe` inspects the zip directory
  listing (total uncompressed size, per-entry compression ratio) before
  openpyxl unzips anything; rejects with HTTP 400. (Laravel's 10MB on-disk
  cap in `StoreImportRequest.php` already existed — the gap was specifically
  the *decompressed* size, not the upload size.)
- **Cap row/cell count on xlsx uploads** — the zip-bomb guard limits
  decompressed *byte size*, not row count. A file with an enormous number of
  thin rows could still pass and be slow to iterate (CPU, not memory).
  Revisit if a real file shows this in practice.
- ~~**Batched inserts / `COPY` for large files**~~ Partially resolved
  2026-07-11 — `upsert_customers` now writes the whole file in one
  `unnest()`-based statement (1k rows: 0.2s → 0.04s) and embeddings via
  `executemany`. Still open for 100k+: `reader.py` loads the whole file into
  memory; revisit chunked reads + `COPY` when a real file that size shows up.

## From upload perf fix, 2026-07-11

- **First-import embed wall at ~9k new rows** — embedding measured at
  ~13ms/row (fastembed bge-small, CPU): 10k brand-new rows ≈ 130s of
  embedding, which blows Laravel's 120s `/process` timeout. Unchanged rows
  are now skipped, so this only bites imports where most rows are new or
  changed. Options when it bites: raise the timeout, embed in the background
  (needs the R2+ queue), or tune fastembed batching/parallelism.
- **python-service has no automated tests** — the perf rewrite was verified
  manually. Cases worth locking in when a test harness lands: new-vs-update
  upsert correctness, customer row ↔ embedding written in one transaction,
  duplicate `customer_code` in a file rejected at validation, unchanged
  re-upload skips embedding.

## From RAG feature (customers /ask), 2026-07-09

- **Sanctum API-token issuance for the future Flutter mobile client** — the
  next explicit milestone, not part of this one. The app is 100% session
  (cookie) auth today; a mobile client needs its own token-login endpoint
  (`$user->createToken(...)`) since it can't share a browser cookie jar.
  `personal_access_tokens` table already exists (Sanctum default install,
  currently unused) — the issuance endpoint is the missing piece.
- **Rate limiting on `POST /customers/ask`** — no per-request cap beyond
  requiring a logged-in session; a malicious or buggy client could run up
  Groq API usage. Revisit if cost becomes a real concern.
- **`.env.example` references a non-existent
  `docs/security/06-secrets-management.md`** — stale, unrelated to this
  feature, noticed while adding `GROQ_API_KEY`. Either write that doc or fix
  the reference (the real security docs are `00`–`03` in `docs/security/`).

## From RBAC users admin, 2026-07-10

- **Guard the last-admin invariant on the role-EDIT path too** — the
  `UserController` guard blocks removing `roles.manage` from its last holder
  when assigning roles to a user, but `RoleController::update` can strip
  `roles.manage` off the `admin` role itself and orphan everyone. Same
  invariant, different door. **Must ship with the RolesView milestone.** See
  `docs/security/04-privilege-management.md`.
- **Last-admin guard is not race-safe** — two concurrent requests each
  removing a different one of the last two admins can both pass and land
  zero. Wrap check + `syncRoles` in a row-locked transaction if it ever
  matters (negligible at current admin count).
- **Confirm-on-every-save may be too much** — Users confirms both role
  changes (destructive: can lock out) and plain detail edits (harmless).
  Consider reserving `confirm()` for destructive actions and letting
  ordinary saves just toast. UX call, not urgent.
- **User detail-editing is scope beyond R1** — editing name/email landed as
  an add-on to the RBAC milestone (user-requested). Keep or split out later;
  no `password`/`roles` mutation lives there, so it's low-risk as-is.
- **`useConfirm` has no dialog queue** — a second `confirm()` while one is
  open overwrites the first's resolver (earlier promise never settles).
  Fine for one-dialog-at-a-time UI; add a queue if that assumption breaks.

## From CRM pivot planning, 2026-07-12

- **Coverage (backup-access delegation)** — `Coverage` model/table, and the
  two `orWhereIn` branches in `Customer::scopeVisibleTo` that grant a
  covering rep temporary visibility (whole-book or single-customer). Shipped
  2026-07-11 but isn't in `new__plan.md`'s spec and its own purpose wasn't
  clear on review — parked rather than kept live. `scopeVisibleTo` is
  simplified to "admin bypass, else owner-only" in CRM phase P5; the
  `coverages` table/model/`CustomerPolicy` reference stay in place (dropping
  the migration is data-destructive) but go unqueried. Revisit only when a
  real backup-coverage need shows up, and re-derive the requirement from
  scratch rather than reviving this shape as-is.
- **GPS on visits (lat/long at time-in/out)** — matches `new__plan.md`'s own
  "Future Features (Not MVP)" list (GPS Check-in). `Visit.started_at`/
  `ended_at` ship without location capture. Revisit alongside Territory
  Management / GPS Check-in if that ever becomes a real requirement.

## From CRM pivot P2 (prospects), 2026-07-13

- **Import↔CRM customer duplication** — a CRM-created customer (converted
  prospect, manual add) gets an auto-generated, sequential code from
  `customer_codes_seq` (ADR 0004 addendum, 2026-07-14), which never matches a
  real spreadsheet code — so a later CSV import of that same real company
  won't match on `ON CONFLICT (customer_code)` and creates a duplicate needing
  manual reconciliation. Acceptable now; revisit with a customer merge/dedup
  feature if it becomes a real operational pain.
- **Prospects still hard-delete** — `ProspectController::destroy()` does a
  real `DELETE`. P4 introduced soft-delete (+`deleted_by`) as the standing
  convention for CRM business records (see `CLAUDE.md` Conventions); Prospects
  predates that decision and needs retrofitting — add `deleted_at`/`deleted_by`
  to `prospects`, `SoftDeletes` on the model, stamp the actor on delete, same
  shape as `VisitPlanEntry`.

## From CRM pivot P4 (visit plans), 2026-07-14

- ~~**Freeze plan entries once `planned_date <= today`**~~ Resolved
  2026-07-14 — enforced server-side in `VisitPlanService::addEntry`/
  `removeEntry` + `VisitPlanEntryPolicy` (not just a disabled button), and
  reflected in the web UI (past/today days render read-only). See PLAN.md's
  P4 row.
- ~~**Visit→plan auto-link**~~ Resolved 2026-07-14 — `VisitService::start()`
  matches rep + customer + today's date against open `VisitPlanEntry` rows
  and sets `Visit.visit_plan_entry_id` in the same insert, with app-level
  dedup against double-claiming (ADR 0005; the DB-level unique constraint is
  deferred — see ADR 0005's "Revisit when").
- **Planned-vs-actual coverage report** — still unbuilt. This was the whole
  point of the auto-link and the freeze (both now shipped); nothing consumes
  them yet. The natural next CRM-adjacent milestone.
- **No `VisitPlanSeeder`** — the plan screen has no demo data on a fresh DB,
  unlike other CRM entities. Add one when convenient.
