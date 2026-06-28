# OWASP Top 10 (2021) — mapped to csv-importer

Quick reference. Each item: the risk + what we do about it in this project.

### A01 — Broken Access Control
A user reads/changes another user's imports.
- **Control:** auth required on every `/api/imports*` route; ownership check
  (`import.user_id == current_user.id`) before returning or mutating. See `03-...md`.

### A02 — Cryptographic Failures
Secrets or data exposed in transit/at rest.
- **Control:** HTTPS in production; DB password only in env; never log file contents or creds.

### A03 — Injection
SQL injection via filename or row data; **CSV formula injection** in exported data.
- **Control:** parameterised queries / Eloquent + SQLAlchemy bound params — never string-build SQL.
  Sanitise cells starting with `= + - @` if we ever re-export. See `02-...md` and `04-...md`.

### A04 — Insecure Design
No rate limit on uploads; unbounded file size → resource exhaustion.
- **Control:** max file size, max rows, throttle upload endpoint, async worker so one big
  job can't take down the API.

### A05 — Security Misconfiguration
`APP_DEBUG=true` in prod, default Postgres password, open ports.
- **Control:** debug off in prod, change default creds, expose only needed ports. See `05-...md`.

### A06 — Vulnerable & Outdated Components
Old pandas/Laravel/npm packages with CVEs.
- **Control:** `composer audit`, `npm audit`, `pip-audit` in CI. See `07-...md`.

### A07 — Identification & Authentication Failures
Weak login, no lockout, guessable sessions.
- **Control:** Laravel Sanctum/Breeze auth, password hashing (bcrypt/argon), rate-limited login.

### A08 — Software & Data Integrity Failures
Untrusted file deserialization (pickle!), unsigned dependencies.
- **Control:** **never** `pickle.load` untrusted data; pin dependency hashes; parse files
  with safe readers only.

### A09 — Security Logging & Monitoring Failures
No record of who uploaded what / failed parses unseen.
- **Control:** log each import (user, filename, status, error) — but **never** log row data
  (it's customer PII) or secrets.

### A10 — Server-Side Request Forgery (SSRF)
Low risk here (we don't fetch URLs from user input). Stay alert if we ever add
"import from URL" — validate + allowlist the host.
