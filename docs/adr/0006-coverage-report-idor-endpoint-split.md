# 0006 — Coverage report: endpoint split over representative_id param

## Context

Planned-vs-actual coverage report needed two views: a rep checking their own
week, an admin checking every rep's week. Both read from the same
`VisitPlanEntry` data, just scoped differently.

## Problem

How does the report know *whose* week to return, without letting one rep
read another rep's data?

## Options

- **A**: one endpoint (`GET /reports/coverage`), takes an optional
  `representative_id` query param, server validates the caller either owns
  that id or holds `roles.manage`.
- **B**: two endpoints — `GET /reports/coverage/my-week` (identity always
  `$request->user()`, gated `visits.view`) and `GET /reports/coverage/team`
  (every rep, gated `roles.manage`). Neither route accepts a representative
  id as input at all.

## Decision

**B.** No endpoint on this feature ever reads a representative id from the
client. `my-week` always means the session user; `team` always means all
reps and requires the admin permission.

## Consequences

**Good:** the IDOR class (forge another rep's id, read their week) doesn't
just get rejected — it's structurally impossible, since there's no id input
to forge. `ShowCoverageReportRequest::authorize()` can safely be `true`
because there's no per-row ownership left to check. Confirmed by a
regression test (`test_a_representative_id_query_param_is_ignored_not_honored`)
that proves a spoofed `representative_id` query param has zero effect, not
a 403 or validation error.

**Bad:** two routes/controller methods instead of one, some structural
duplication (`resolveWeekStart`, `ShowCoverageReportRequest` shared, but the
handler bodies differ). If a third view shows up (e.g. "admin looks at one
specific rep's week"), this pattern doesn't extend cleanly — see Revisit.

## Revisit when

A third access pattern is needed — e.g. an admin drilling into one specific
rep's week by id. At that point option A's per-row ownership check becomes
unavoidable somewhere, and it's worth deciding whether to add a third route
(`/reports/coverage/rep/{uuid}`, still no free-form param, id from route
binding) rather than reopening `representative_id` as a query param.
