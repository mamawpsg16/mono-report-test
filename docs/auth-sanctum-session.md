# Auth: Sanctum Session-Based Login

How DataForge logs users in. **Cookie/session auth** (SPA mode), not API tokens.
Vue SPA (`:8010`) talks to Laravel API (`:8009`) using Laravel Sanctum's
"stateful" first-party flow.

---

## 1. Why session, not tokens

Two styles Sanctum supports:

| Style | How | Used here? |
| --- | --- | --- |
| **API tokens** | `Bearer xxx` header, stored in JS | ❌ no |
| **SPA / session** | encrypted cookie, CSRF protected | ✅ yes |

Session style = browser holds an `httpOnly` session cookie. JS never sees the
credential, so it survives XSS better. Trade-off: only works when frontend +
backend are **same registrable domain** (here both `localhost`).

---

## 2. The request flow (what happens on "Sign in")

```
Browser (localhost:8010)                    Laravel API (localhost:8009)
        |                                            |
        |  1. GET /sanctum/csrf-cookie               |
        |------------------------------------------->|
        |  204 + Set-Cookie: XSRF-TOKEN, session     |
        |<-------------------------------------------|
        |                                            |
        |  2. POST /api/login                        |
        |     Cookie: session                        |
        |     X-XSRF-TOKEN: <from cookie>            |
        |     {email, password}                      |
        |------------------------------------------->|
        |                                            |  Auth::attempt()
        |                                            |  session()->regenerate()
        |  200 {user}  + refreshed session cookie    |
        |<-------------------------------------------|
        |                                            |
        |  3. GET /api/user (and all API calls)      |
        |     Cookie: session  (auto by browser)     |
        |------------------------------------------->|
        |  200 {user}   (auth:sanctum passes)        |
        |<-------------------------------------------|
```

**Step 1 — CSRF cookie.** Before any state-changing POST, the SPA calls
`GET /sanctum/csrf-cookie`. Laravel sets two cookies: `XSRF-TOKEN` (readable by
JS) and the session cookie (`dataforge_session`, httpOnly).

**Step 2 — Login.** Axios reads the `XSRF-TOKEN` cookie and echoes it back in the
`X-XSRF-TOKEN` header. Laravel's CSRF middleware checks header == cookie. If
creds valid, `session()->regenerate()` issues a fresh authenticated session.

**Step 3 — Authenticated requests.** Browser auto-sends the session cookie on
every same-site request. `auth:sanctum` sees a valid first-party session and
lets the request through. No token juggling in JS.

---

## 3. Frontend setup (Vue + axios)

[frontend/src/helpers/api.js](../frontend/src/helpers/api.js) — three flags make
the cookie flow work:

```js
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000',
  headers: { Accept: 'application/json' },
  withCredentials: true,   // send + receive cookies cross-port
  withXSRFToken: true,     // auto-copy XSRF-TOKEN cookie -> X-XSRF-TOKEN header
})
```

- `withCredentials: true` — without it, browser drops the session cookie on the
  cross-origin (`:8010` -> `:8009`) call. Auth silently fails.
- `withXSRFToken: true` — axios reads `XSRF-TOKEN` cookie, sets the header. Skip
  it and every POST gets **419 CSRF token mismatch**.
- `baseURL` must point at the **host-published backend port** (`:8009`), injected
  via `VITE_API_URL`. The fallback `:8000` is wrong for this stack — see §6.

A `401` interceptor bounces the user back to `/login`.

---

## 4. Backend setup (Laravel + Sanctum)

### a. Stateful middleware
[backend/app/Http/Kernel.php](../backend/app/Http/Kernel.php) — the `api` group
gets Sanctum's stateful middleware first, so first-party requests use the web
session guard:

```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    // ...
],
```

### b. Login route + controller
[backend/routes/api.php](../backend/routes/api.php):

```php
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    // ...protected routes
});
```

[AuthenticatedSessionController](../backend/app/Http/Controllers/Auth/AuthenticatedSessionController.php)
— `Auth::attempt()` then `session()->regenerate()` (prevents session fixation).
Logout does `logout()` + `session()->invalidate()`.

### c. Config that must line up

| Config | File | Value here | Why |
| --- | --- | --- | --- |
| Stateful domains | `config/sanctum.php` | `SANCTUM_STATEFUL_DOMAINS` env | Lists which origins get session auth. **Must include the frontend host:port.** |
| Session guard | `config/sanctum.php` | `'guard' => ['web']` | Session auth uses the web guard. |
| CORS paths | `config/cors.php` | `['api/*', 'sanctum/csrf-cookie']` | Both the API and the CSRF endpoint must allow cross-origin. |
| CORS origin | `config/cors.php` | `FRONTEND_URL` env | Exact frontend origin (no `*` allowed with credentials). |
| CORS credentials | `config/cors.php` | `supports_credentials => true` | Required so the browser keeps the cookie. |
| Session domain | `config/session.php` | `SESSION_DOMAIN=localhost` | Cookie shared across `:8010` and `:8009` (same host, diff port = same cookie domain). |
| Same-site | `config/session.php` | `lax` | OK because both sides are `localhost`. |

---

## 5. Environment variables (.env)

```env
APP_PORT=8009
FRONTEND_PORT=8010

# Sanctum: which origins are first-party (get session auth)
SANCTUM_STATEFUL_DOMAINS=localhost:8010,localhost:8009,127.0.0.1:8010,127.0.0.1:8009

# Cookie shared across both ports
SESSION_DOMAIN=localhost

# Exact frontend origin for CORS (credentials = no wildcard)
FRONTEND_URL=http://localhost:8010

# Browser-side API base — passed into the Vite build (see docker-compose)
VITE_API_URL=http://localhost:8009
```

> Note: `localhost:8010` and `localhost:8009` are **different origins** (port
> differs) for CORS, but **same cookie domain** (`localhost`) for the session
> cookie. That split is exactly why CORS config and `SESSION_DOMAIN` both matter.

`VITE_API_URL` reaches the frontend container via
[docker-compose.yml](../docker-compose.yml):

```yaml
frontend:
  environment:
    VITE_API_URL: http://localhost:${APP_PORT:-8000}
```

---

## 6. Common failures (seen during setup)

| Symptom | Cause | Fix |
| --- | --- | --- |
| "Something went wrong", network shows request to `:8000` | `VITE_API_URL` not passed to frontend container; axios fell back to `:8000` | Add `environment: VITE_API_URL` to frontend service, recreate, hard-reload browser |
| **419** CSRF token mismatch | XSRF header missing, or CSRF cookie call skipped | `withXSRFToken: true`; always GET `/sanctum/csrf-cookie` before login |
| **401** even with right creds | Frontend origin not in `SANCTUM_STATEFUL_DOMAINS`, or `withCredentials` off | Add host:port to stateful domains; set `withCredentials: true` |
| CORS error in console | Origin not in `FRONTEND_URL`, or `supports_credentials` false | Match origin exactly; enable credentials |
| Cookie not stored | `SESSION_DOMAIN` mismatch | Set to shared parent host (`localhost`) |
| 500 / "table users not exist" | DB not migrated/seeded | `php artisan migrate --force --seed` (now auto via entrypoint) |

---

## 7. Quick manual test (curl)

```sh
J=cookies.txt
# 1. get csrf cookie
curl -s -c $J -b $J -H "Origin: http://localhost:8010" \
  http://localhost:8009/sanctum/csrf-cookie

# 2. extract token, url-decode, post login
XSRF=$(grep XSRF-TOKEN $J | awk '{print $7}' \
  | python3 -c "import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))")
curl -s -w "\nHTTP %{http_code}\n" -c $J -b $J \
  -H "Origin: http://localhost:8010" -H "Accept: application/json" \
  -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $XSRF" \
  -X POST http://localhost:8009/api/login \
  -d '{"email":"admin@dataforge.test","password":"password"}'
```

Expect `HTTP 200` + `{user: {...}}`.

**Seed admin:** `admin@dataforge.test` / `password`.
