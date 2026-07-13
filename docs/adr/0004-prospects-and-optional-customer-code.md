# 0004 — Prospects, and making customer_code optional

## Context

CRM pivot P2 introduces **prospects**: leads a sales rep is chasing before
they become a real `Customer`. A rep captures a prospect (name/phone/notes),
works it, and eventually **converts** it into a customer.

Until now, every `Customer` came from CSV import, so the table encoded
import-era assumptions as `NOT NULL`:
- `customer_code` — the natural key the Python importer diffs and upserts on
  (`INSERT ... ON CONFLICT (customer_code)`).
- `email` — made `NOT NULL` earlier for the (now-superseded) rewards/mobile
  customer-login direction.
- `original_filename` — the source file a row came from.

A CRM-native customer — one converted from a prospect, or hand-entered — has
none of these.

## Problem

1. How do we let CRM-created customers exist without a spreadsheet code, an
   email, or a source filename — without breaking the CSV import pipeline that
   depends on `customer_code`?
2. What does "convert a prospect to a customer" do to the prospect row?

## Options

**customer_code:**
- (a) Keep it required; convert auto-generates or prompts for a code.
  *Rejected* — forces meaningless/fake codes onto every CRM customer.
- (b) Remove `customer_code` entirely; key imports on something else.
  *Rejected* — imports need a stable per-row key to be idempotent; without it,
  re-uploading a file can't tell "update" from "new" and duplicates everyone.
- (c) **Make `customer_code` (and `email`, `original_filename`) nullable.**
  *Chosen* — `customer_code` becomes an optional *source/external* code carried
  only by imported rows; CRM-native rows leave it null.

**Convert semantics:**
- (a) Move/rename the prospect row into the customers table.
  *Rejected* — destroys the prospect's own identity and (future) visit history.
- (b) **Create a new `Customer`, keep the `Prospect` row, stamp
  `prospects.converted_customer_id`.** *Chosen* — the Salesforce Lead→Contact
  pattern; history stays on the prospect, the link is explicit.

## Decision

- New `prospects` table: `uuid`, `name`, `phone`, `notes`, `created_by`,
  nullable `converted_customer_id`.
- `customer_code`, `email`, `original_filename` → **nullable**
  (migration `2026_07_13_000001_relax_customer_import_columns`).
- **Convert** creates a `Customer` (in one transaction) and stamps the
  prospect's `converted_customer_id`; the prospect row persists.
- New `prospects.*` permission module; reps hold it. Row-level scoping via
  `Prospect::scopeVisibleTo` (own rows) + `ProspectPolicy` on update/delete.

## Consequences

**Good**
- CRM-native customers are valid with no fake data.
- Imports are unaffected: files still supply `customer_code`, and Postgres
  allows multiple NULLs under the existing `UNIQUE(customer_code)`.
- A prospect's identity/history survives conversion.
- Prospects reuse the established scope+policy pattern (consistent with
  customers), so the security model is uniform.

**Bad / locks us into**
- `customer_code` is no longer a guaranteed identifier: any code assuming it's
  present must tolerate null (frontend column display, RAG `sources`).
- A CRM-created customer (null code) won't auto-match a later CSV import of the
  same real company → possible duplicate needing manual reconciliation
  (tracked in `docs/backlog.md`).
- `email` nullable reverses the earlier `NOT NULL`; anything that assumed a
  customer always has an email must handle absence.

## Revisit when

- We add a customer merge/dedup feature (would address the import↔CRM
  duplicate case), **or**
- We reintroduce customer accounts / mobile login that genuinely require a
  non-null email, **or**
- Import-vs-CRM duplication becomes a real operational pain.

## Addendum (2026-07-14) — auto-generate customer_code on Eloquent creation

`customer_code` stays nullable in the schema (unchanged), but `Customer` now
auto-generates a 10-digit, zero-padded, sequential code (e.g. `0000000001`) in
a `creating` hook whenever one isn't supplied — mirroring how `HasPublicUuid`
sets `uuid`. The number comes from a **dedicated Postgres sequence**
(`customer_codes_seq`, migration `2026_07_14_000004_create_customer_code_
sequence`), not the row's own `id` — `id` is shared by every customer row
including all ~1000 imported ones that don't need a generated code, so tying
to it would start the count wherever that sequence happened to already be
(observed: 8019, from accumulated test-suite churn) instead of a clean 1. The
dedicated sequence only advances when a code is actually generated. Like any
Postgres sequence it's non-transactional (a rolled-back test still consumes a
value), so gaps over time are normal, not a bug, same as `id` itself. `creating`
fires exactly once per row, before insert, so a later edit to an existing
customer never regenerates or changes its code — stable for the row's
lifetime, same as `uuid`. This only fires for rows created through Eloquent;
`python-service` writes imported rows via raw SQL and is untouched, so
imported rows keep their real spreadsheet code exactly as before. Effect: a
CRM-created customer (future prospect-convert, manual add) never shows a blank
Customer Code in the UI, and codes are strictly increasing with no
collision-checking needed. No prefix: on the rare chance a real import code
ever collides with a generated one, the existing `UNIQUE(customer_code)`
constraint throws loudly rather than silently duplicating — acceptable,
unhandled for now. This does **not** change the import/CRM
duplicate-detection tradeoff above — a generated code still won't
*proactively* match a real import code for the same company, so that risk is
unchanged, just no longer visible as a blank cell.
