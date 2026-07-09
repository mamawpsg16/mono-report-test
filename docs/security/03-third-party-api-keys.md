# Third-party API keys (Groq)

The RAG `/ask` feature (`python-service/rag.py`) calls the Groq API for answer
generation. This is the project's first outbound call to a third-party service
using a secret key — the risks below apply to any future API key too (OpenAI,
Anthropic, etc.), not just Groq.

## Why it matters

An API key is bearer credentials: whoever holds it can spend against your account
(cost) and act as you against that provider (abuse, rate-limit exhaustion, data
sent to the provider on your behalf). Unlike a session cookie, it doesn't expire
on logout and isn't scoped to a single user — leaking it once means rotating it
everywhere it's used.

## Real-world attack example

A key committed to a public GitHub repo (even briefly, even in history) gets
scraped by bots within minutes — this is a well-documented, automated attack
pattern, not a hypothetical. The attacker runs it up to the account's spend
limit or uses it for unrelated abuse before the owner notices.

## How this project mitigates it

| Check | How |
|-------|-----|
| Never in code or git | `GROQ_API_KEY` lives only in `.env` (git-ignored); `.env.example` documents the variable name with an empty value, never a real key |
| Not exposed to the browser | Only `python-service` reads it (`os.getenv("GROQ_API_KEY")` in `rag.py`); Laravel proxies the `/ask` request server-to-server, so the key never appears in frontend JS or network responses the browser can see |
| Fails loud, not silent | Missing key raises `RagConfigError`, surfaced as a clean HTTP 500 with a real message (`routers/customers.py`) — not swallowed, not logged with the key value |
| Least exposure | The key is passed via `env_file: .env` in `docker-compose.yml`, scoped to the `python-service` container only — `backend` and `frontend` never receive it |

## What risk remains

- **No key rotation process yet.** If the key leaked, the fix today is manual:
  revoke it in the Groq console, generate a new one, update `.env`, restart
  `python-service`. No automated detection of a leaked key.
- **No per-request rate limiting on `/customers/ask`.** A malicious or buggy
  client could hammer the endpoint and run up Groq usage; `auth:sanctum` at
  least requires a logged-in session, but that's not a spend cap. Revisit if
  this becomes a real cost concern (`docs/backlog.md`).
- **`.env` on disk is only as safe as the host machine.** Standard risk for any
  local-secrets setup; a real deployment would use a secret manager instead.
