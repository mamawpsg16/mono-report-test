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
- **Batched inserts / `COPY` for large files** — `upsert_customers` loops
  row-by-row and `reader.py` loads the whole file into memory. Fine for the small
  files we test with; revisit chunked reads + batched `INSERT`/`COPY` when a real
  file size (name the number then — e.g. >10k rows) justifies it.

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
