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

- **Harden xlsx uploads against malicious files** — `read_xlsx` (openpyxl) now
  parses `.xlsx`, which is a zip container. No **upload size cap** and no
  **zip-bomb / decompression guard** exist yet, so a small crafted file could
  expand to exhaust memory. Add a size limit at the Laravel boundary and a
  decompressed-size / cell-count guard before parsing. Revisit before real users
  can upload, or before uploads are exposed beyond trusted testers.
- **Batched inserts / `COPY` for large files** — `upsert_customers` loops
  row-by-row and `reader.py` loads the whole file into memory. Fine for the small
  files we test with; revisit chunked reads + batched `INSERT`/`COPY` when a real
  file size (name the number then — e.g. >10k rows) justifies it.
