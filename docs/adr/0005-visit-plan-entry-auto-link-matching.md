# 0005 — Visit-to-plan auto-link: matching rule and race-safety scope

## Context

CRM pivot P4 built `VisitPlan`/`VisitPlanEntry` (a rep's *intent* — which
customers to see on which day) as a table fully disconnected from `Visit` (P3,
the actual check-in/check-out event). The `visits.visit_plan_entry_id` column
was added in a deferred migration (`2026_07_14_000003`, nullable, FK to
`visit_plan_entries`, `nullOnDelete`) once `visit_plan_entries` existed, but
nothing populated it. Per an earlier decision, linking a started visit to its
planned entry must be **automatic** — set inside `VisitService::start()`,
triggered from the mobile field workflow — with no manual "tick" UI on web or
mobile. Without this link, no future "planned vs. actually visited" coverage
report has anything to join against.

## Problem

Two questions `VisitService::start()` had to answer: (1) which fields identify
"this visit fulfils that planned entry" (rep, customer, day), and (2) what
happens if two visits could plausibly claim the *same* entry — a normal-use
case (rep genuinely visits the same customer twice in a day) as well as a
theoretical race (two concurrent "Start Visit" requests).

## Options

1. **Naive match, no dedup** — `where` on rep + customer + today's date,
   `first()`. Simplest. Risk: if a customer is visited twice in one day, or two
   requests race, **two different `Visit` rows could both claim the same
   `VisitPlanEntry`**, silently corrupting any future planned-vs-actual report
   — the entire reason this feature exists.
2. **Match + app-level dedup** — same match, plus a `whereDoesntHave('visit')`
   clause excluding entries another visit has already claimed (needs a new
   `VisitPlanEntry::visit()` `hasOne` relation). Closes the corruption risk
   under normal, non-concurrent usage with one extra query clause.
3. **Option 2 + a DB-level unique constraint** on
   `visits.visit_plan_entry_id`. This codebase already has precedent for
   exactly this shape of guarantee: the one-open-visit-per-rep rule is
   app-checked *and* backed by a partial unique index, specifically because an
   app-level pre-check alone isn't race-safe under concurrent requests. Same
   reasoning would apply here.

## Decision

**Option 2 now; Option 3's DB constraint deferred to `docs/backlog.md`, not
built.** The race Option 3 protects against — two concurrent "Start Visit"
requests for the same customer on the same day, landing close enough together
to both pass the `whereDoesntHave` check before either saves — is the same
class of low-probability, low-current-stakes risk this project already
accepted as backlog-level on the last-admin guard ("wrap in a row-locked
transaction if it ever matters"). Option 2 eliminates the risk that actually
bites under ordinary use (a naive match letting two unrelated visits silently
double-claim one entry) for the cost of one relation and one query clause;
Option 3 is real hardening but is additive, not a prerequisite, so it didn't
need to block this milestone.

Match criteria, concretely: the `VisitPlanEntry`'s owning `VisitPlan` belongs to
the visiting rep, `customer_id` matches, `planned_date` equals today
(`whereDate` against `now()`), and no other `Visit` already references it. The
match runs before the new `Visit` is saved, so the link is written in the same
`INSERT`, not a follow-up `UPDATE`.

## Consequences

- Report-correctness is protected under normal (non-racing) use immediately.
  A genuine two-visits-same-customer-same-day case correctly links only the
  first visit; the second gets `visit_plan_entry_id = null` rather than
  stealing the first's link.
- `VisitPlanEntry` and `Visit` now have an **implicit one-to-one relationship**
  (one entry, at most one linked visit) enforced only at the application
  layer. Until Option 3 ships, two truly concurrent requests could still both
  pass the pre-check and both save — an accepted, tracked gap, not a silent
  unknown.
- The match is a plain indexed query run once per `start()` call — negligible
  cost, no background job or DB trigger, keeps the whole rule readable in one
  method (`VisitService::matchPlanEntry`).

## Revisit when

- Mobile ships and real usage shows the race is more than theoretical (e.g.
  offline sync replaying near-simultaneous starts, or rapid duplicate taps
  reaching the server as two requests) — add the partial/unique DB constraint
  on `visits.visit_plan_entry_id`, mirroring `visits_one_open_per_representative`.
- The `planned_date <= today` freeze (parked, `docs/backlog.md`) ships — at
  that point, once a day is frozen, the matching window for that day is closed
  too, which narrows the race further and may lower the urgency of Option 3.
