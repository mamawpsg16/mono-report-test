# 0001 — xlsx support via openpyxl

## Context

PLAN.md originally locked parsing to Python stdlib `csv` + `psycopg` (v3),
explicitly ruling out `pandas`/`openpyxl`/`psycopg2` to keep the pipeline
simple while the upload feature was small. Laravel's upload validation
(`StoreImportRequest`) has always accepted `.xlsx` (content-checked via
`mimetypes`), but `python-service/reader.py` only ever implemented
`read_csv` — an uploaded `.xlsx` would pass Laravel's validation, reach
python-service, and be parsed as if it were a CSV (garbage rows or a crash).

## Problem

We want real xlsx upload support, not just an accepted-but-broken label.
xlsx is a zip container holding XML sheet data — there is no stdlib parser
for it. Some third-party library is unavoidable if xlsx is to be read for
real.

## Options

1. **openpyxl** — pure-Python, reads/writes xlsx, supports a streaming
   `read_only=True` mode (doesn't load the whole sheet into memory at once).
   Actively maintained, the de facto standard for xlsx in Python.
2. **pandas** (with an xlsx engine) — would also solve it, but drags a large
   dependency tree for a single read operation. This is the exact weight
   PLAN.md avoided pandas for originally.
3. **Drop xlsx support** — tighten Laravel's allow-list to CSV-only,
   matching actual capability. No new dependency, but removes a feature
   Laravel already advertised as accepted.

## Decision

Use **openpyxl**, in `read_only=True` mode. This reverses the earlier
"not openpyxl" line in PLAN.md's locked decisions specifically for xlsx
*reading* — pandas remains out of scope.

## Consequences

- New third-party dependency in `python-service/requirements.txt`
  (`openpyxl==3.1.5`), pulled into the Docker image on next build.
- `reader.py` gains a content-sniffing dispatcher (`read_customers_file`)
  that checks the file's real magic bytes (zip signature `PK\x03\x04`)
  rather than trusting the stored file's extension, then routes to
  `read_csv` or `read_xlsx` accordingly.
- openpyxl returns typed cell values (`int`, `float`, `datetime`, `None`),
  not strings — `read_xlsx` must normalize every cell to a string (or
  empty string) so rows match the string-keyed dict shape `validator.py`
  and `customers.py` already assume from `csv.DictReader`.
- Locks us into maintaining two parse paths (CSV + xlsx) instead of one.

## Revisit when

- openpyxl's maintenance stalls or a security advisory lands against it.
- We need to *write* xlsx (exports) — that's a separate decision, not
  covered here.
- Upload volume/size grows enough that streaming isn't good enough and a
  background/async pipeline becomes necessary — see PLAN.md's other
  upload roadmap items (in-memory + row-by-row upsert).
