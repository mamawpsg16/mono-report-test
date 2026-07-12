# Deferred security items

Security behaviors we consciously turned off or postponed, with the reason and
the condition to revisit. Each entry: what's deferred, why, the residual risk,
and when to re-address.

---

## Sanctum `AuthenticateSession` disabled (2026-07-13)

**What:** Sanctum's `AuthenticateSession` middleware is commented out in
`backend/config/sanctum.php` (`middleware.authenticate_session`).

**Why:** It was flushing an authenticated session back to a guest session (→ 401,
bounce to `/login`) on the first page refresh after switching accounts
(log out A → log in B → refresh). Confirmed by disabling it: the account-switch
flow then works. The exact misfire mechanism wasn't fully root-caused — a
source-trace of the middleware suggested it shouldn't flush on a clean switch,
but it did. Disabling it is a common, idiomatic choice for Sanctum SPAs.

**Residual risk:** The middleware's job is to invalidate a user's *other* active
sessions when their password changes (so a stolen/old session dies on password
reset). With it off, changing a password via `/api/change-password` no longer
force-logs-out that user's other devices/sessions — those stay valid until they
expire normally (`SESSION_LIFETIME`, 120 min idle). Low impact for a
single-device back-office app in its current state; matters more once accounts
are shared across devices or a "log out everywhere" expectation exists.

**Revisit when:** we add real multi-device usage, a password-reset/"log out other
sessions" feature, or before any production deployment handling real user data.
Re-enabling means uncommenting the line AND root-causing the account-switch
flush first (otherwise the original bug returns).
