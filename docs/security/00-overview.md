# Security notes — overview

This folder = one markdown file per security topic, written for **this** project
(file-upload → Python worker → Postgres). Each file: what the risk is, why it matters
here, and the concrete control we apply.

| File | Topic | Most relevant because… |
|------|-------|------------------------|
| `01-owasp-top-10.md` | OWASP Top 10 mapped to our app | baseline checklist |
| `02-file-upload-security.md` | Upload + CSV/XLSX parsing risks | **our core attack surface** |
| `03-authentication-authorization.md` | Who can upload / see imports | every endpoint is a door |
| `04-database-security.md` | Postgres, SQL injection, least privilege | two apps share one DB |
| `05-docker-security.md` | Container hardening | we ship everything in Docker |
| `06-secrets-management.md` | `.env`, DB creds, keys | secrets leak = game over |
| `07-dependency-security.md` | Supply chain (composer/npm/pip) | most code is other people's |

## Guiding principles

1. **Validate at the boundary.** Never trust the uploaded file's name, extension, MIME,
   size, or contents. Check all of them server-side.
2. **Least privilege.** The Python worker's DB user only needs INSERT/UPDATE on import
   tables — not DROP, not superuser.
3. **Defense in depth.** Multiple weak checks beat one perfect check.
4. **Fail closed.** On any validation error → reject + log, never "process anyway".
5. **Secrets out of code.** All creds via env, never committed.

Threat model in one line: *an attacker uploads a malicious file (or a huge one) and tries
to crash us, run code, inject SQL, or read other users' data.*
