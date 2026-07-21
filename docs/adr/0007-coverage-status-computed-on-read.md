# 0007 — Coverage entry status: computed on read, not stored

## Context

The coverage report tags every `VisitPlanEntry` with one of three statuses:
`visited` (a Visit auto-linked to it), `missed` (its planned day is past and
nothing visited it), or `pending` (today or a future day, not yet visited).
`CoverageReportService::statusFor` derives this at read time from the entry's
`visit` relation and `planned_date`.

## Problem

Should the status live as a stored/denormalized column on `visit_plan_entries`
(written and kept in sync), or be computed on every read? A stored column is
the usual instinct — cheaper reads, sortable/filterable in SQL.

## Options

- **A — stored `status` column.** Default `pending` on create; flip to
  `visited` in `VisitService::start()`'s auto-link; flip to `missed` when the
  planned day passes unvisited.
- **B — computed on read.** No column. `statusFor` derives the value each time
  the report is built, from `visit` presence and `planned_date` vs today.

## Decision

**B.** Status is derived on read; no column is stored.

The deciding factor is the `pending → missed` transition. Two of the three
statuses have a natural write event to hook a column update onto:

- `pending` — set on entry creation.
- `visited` — set by `VisitService::start()` when a visit auto-links.

`missed` has **no write event**. It happens when a day passes with no visit —
the passage of time, which no code observes. Keeping a stored column correct
would require a scheduled job (cron) that nightly scans entries and flips the
lapsed ones. **This project has no queue and no scheduler** (mail is even sent
synchronously — see PLAN.md). So a stored column would silently go stale: an
entry that truly lapsed yesterday would still read `pending` until some write
happened to touch it, which for a missed entry never comes.

Computing on read sidesteps this entirely: the "event" for `missed` is the
current date, and every read already knows the current date. `planned_date <
today` is always correct, with zero drift and zero infrastructure.

## Consequences

**Good:**
- No drift is possible — the report cannot lie about a missed visit, which
  matters because this report is the anti-gaming artifact for the P4 plan
  freeze. A stored-but-unscheduled column would let a rep "pass" by doing
  nothing (the entry never flips to missed).
- No new infrastructure (queue/scheduler) pulled in just to keep a column
  fresh.
- Status logic lives in one place (`statusFor`), easy to read and test.

**Bad:**
- Status is not queryable/sortable in SQL — you can't `WHERE status = 'missed'`
  or `ORDER BY status` at the database. Filtering/sorting by status would have
  to happen in PHP after the derive, or force a rethink.
- Every read recomputes. Negligible at the current week-at-a-time,
  per-rep volume; would matter at org-wide-history scale.

## Revisit when

- A scheduler/queue enters the project for other reasons (the cheap way to
  keep a stored column fresh would then exist), **and** a use case needs to
  filter/sort large volumes of entries by status in SQL. Both need to be true
  — the scheduler alone doesn't justify denormalizing; the SQL need alone
  doesn't justify a nightly job. Until then, computed-on-read wins.
