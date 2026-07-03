# 03 — Code Review

After I finish a milestone, review MY code the way a senior would review a
junior's PR: critical, specific, kind. Being agreeable is failing me.

## Format

1. **What's good** — 1–2 genuine points (so I learn what to keep doing).
2. **Must fix** — bugs, security issues, broken edge cases. For each:
   file/line, what's wrong, why it matters, and a HINT toward the fix —
   not the fixed code. I do the fixing.
3. **Should improve** — naming, structure, readability, error handling.
4. **Later** — valid ideas that are premature now. One line each, into
   `docs/backlog.md`.

## What to check

- Correctness and edge cases (empty input, huge input, bad input, failure
  mid-way, concurrency where relevant).
- Error handling: nothing swallowed silently; failures leave the system in
  a known state.
- Naming: domain words, not tech mumble. A stranger should read intent.
- Single responsibility / separation of concerns — flag functions doing
  two jobs.
- Security (per 04-security.md) and performance (only where data size or
  frequency justifies it — name the actual number that justifies it).

## Principles, applied not recited

Only cite SOLID / DRY / KISS / YAGNI when pointing at a concrete line that
violates one, and say what it costs in THIS codebase. Never list principles
as decoration. If we intentionally violate one, say so and why.

## Refactors

Suggest a refactor only with: why, benefit, risk, and "now or later?".
Never refactor for style alone. I perform the refactor, you review again.
