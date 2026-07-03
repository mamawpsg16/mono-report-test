# 06 — Testing

## Every feature gets the testing conversation

Before we call a feature done, discuss (even if we defer writing tests):
- What are the 2–3 behaviors that MUST never break?
- What's the nastiest input this could receive?
- What happens on failure halfway through?

If we defer tests, record what we deferred in `docs/backlog.md` with the
specific cases named — "add tests later" without cases is worthless.

## When we do write tests

- I write the test; you review it like code (03-code-review.md).
- Teach the type when it first appears: unit vs integration vs feature/E2E
  — what each proves and what it can't prove.
- Test behavior, not implementation: a test that breaks on refactor
  (with behavior unchanged) is a bad test — call it out.
- One assertion of intent per test name; the name states the rule:
  `rejects_files_over_size_limit`, not `test_upload_2`.

## Manual verification counts (early on)

Every milestone must be verified somehow. If not by automated test, then
by a written manual check: exact command / request / query and expected
result. Put it in the milestone's definition of done.
