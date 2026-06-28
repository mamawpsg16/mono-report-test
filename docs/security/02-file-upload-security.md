# File-upload & parsing security

This is our **biggest attack surface**: we accept files (CSV, XLSX, images for OCR) from
users and process them. Two stages of risk — (A) the upload itself, (B) parsing the contents.

## A. Upload validation (Laravel side, the boundary)

Check **all** of these server-side. Never trust any single signal.

| Check | Why | How (Laravel) |
|-------|-----|---------------|
| Extension allowlist | block `.php`, `.exe`, etc. | `mimes:csv,txt,xlsx,png,jpg,jpeg` |
| Real MIME (sniff bytes) | extension is a lie; `evil.php` renamed `.csv` | `mimetypes:text/csv,image/png,…` (checks magic bytes) |
| Max size | resource exhaustion / DoS | `max:10240` (KB) — tune per type |
| Random stored name | path traversal, overwrite | `->store('imports')` → Laravel generates a UUID name |
| Store **outside** webroot | uploaded file must never be served/executed | `storage/app/imports`, not `public/` |
| Throttle | upload flooding | `throttle:` middleware on the route |

Rule: **store the file, hand the path to the worker — never execute or include it.**

## B. CSV / XLSX parsing risks (Python side)

- **CSV formula injection** (a.k.a. CSV injection): a cell like `=cmd|'/c calc'!A1` runs in
  Excel when someone opens an *export*. We're *importing*, so it's only a risk if we ever
  **re-export** user data. Mitigation: when exporting, prefix cells starting with `= + - @ \t`
  with a single quote. Treat imported cell values as plain data, never evaluate them.
- **Huge files / 100k+ rows** → memory exhaustion. Mitigation: **chunked read**
  (`pd.read_csv(path, chunksize=5000)`), enforce a **max-row cap**, stream don't slurp.
- **Malformed / wrong-column files** → crashes. Mitigation: validate headers first; wrap
  per-row parse in try/except; collect errors, never let one bad row kill the job.
- **XLSX = a zip file** → **zip bomb**: tiny `.xlsx` expands to gigabytes. Mitigation:
  size cap before opening; openpyxl `read_only=True`; consider unzipped-size limits.
- **Never `pickle.load`** or `eval` anything derived from the file (OWASP A08).

## C. Image / OCR risks (Python side)

- **Decompression bomb**: a 50 KB PNG that decodes to 50,000 × 50,000 px → RAM blowup.
  Mitigation: `Image.MAX_IMAGE_PIXELS` cap (Pillow warns/raises by default), check
  dimensions before full decode, size cap on upload.
- **MIME spoofing**: a non-image disguised as `.png`. Mitigation: verify magic bytes;
  `Image.open(...).verify()` before processing.
- **Pillow / Tesseract CVEs**: image libraries have a history of memory bugs. Mitigation:
  keep them patched (`07-...md`), run the importer as a **non-root** user in its container
  so a parser exploit is contained.
- **Strip metadata**: EXIF can carry GPS/PII; don't echo it back or store it unfiltered.

## D. After processing

- Delete or quarantine the source file once imported (don't hoard user PII).
- Log *that* an import happened (user, filename, status) — **never** log the row/text
  contents (customer PII, see `04-...md` and A09).
