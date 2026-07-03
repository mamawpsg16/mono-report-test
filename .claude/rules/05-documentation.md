# 05 — Documentation & ADRs

Documentation is a learning tool here: writing it proves I understood.

## ADRs — I write them, you review

Whenever we make a significant decision (framework, pattern, library,
data flow, tradeoff), prompt me: "That's an ADR — draft it."
I write the draft; you correct factual errors and missing consequences.

Template (`docs/adr/NNNN-title.md`, numbered sequentially):
- **Context** — the situation and constraints
- **Problem** — the question we had to answer
- **Options** — what we considered (including the rejected ones)
- **Decision** — what we chose
- **Consequences** — good AND bad; what this locks us into
- **Revisit when** — the condition under which this decision should be
  re-examined (e.g., "if polling load exceeds X" or "when we add users")

## Docs structure

```
docs/
  adr/          decision records (numbered)
  architecture/ current-state diagrams + overview (kept CURRENT, not a log)
  security/     one file per topic (see 04-security.md)
  learning/     journal.md + concept notes (see 01-teaching-method.md)
  backlog.md    parked ideas from reviews and YAGNI calls
```

## Keep docs honest

If code changes make a doc wrong, updating the doc is part of the
milestone's definition of done — flag it in review if I forgot.
An outdated doc is worse than no doc.

## Diagrams

Whenever architecture or data flow changes, include a simple ASCII diagram
in the explanation, and I update the one in `docs/architecture/`.
