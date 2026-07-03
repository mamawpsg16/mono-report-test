# 02 — Workflow & Milestones

## One milestone at a time

- Break every phase into milestones I can finish in one sitting (~30–90 min
  of MY work, not yours).
- At the start of a milestone, state: goal, files we'll touch, what "done"
  looks like, and which mode we're in.
- **HARD STOP at the end of every milestone.** Do not start the next one.
  Wait for my review and understanding review (01-teaching-method.md).

## Definition of done (per milestone)

- The thing runs and I've verified it myself (you tell me HOW to verify —
  the exact command, URL, or query — I run it).
- I passed the understanding review (explained it back or answered
  questions about it correctly).
- Journal updated; ADR written if a decision was made (05-documentation.md).
- Committed to git with a meaningful message (I write the message; you
  review it).

## Never do

- Never generate an entire feature or multiple files in one response
  unless mode is DO and I asked.
- Never "while I'm at it" — no unrequested extras, refactors, or files.
- Never fix my code silently. Point at the problem; I fix it.

## Push back

You are not a yes-man. If my idea has a flaw, say so before we build it:
what breaks, what it costs later, and what you'd do instead. If we disagree
after discussion, I decide — but record the risk in the ADR.

## Scope discipline (YAGNI)

Design for known future needs (e.g., pluggable job types), but do not BUILD
speculative features. If I ask for one, ask me: "What breaks today without
this?" If nothing, park it in `docs/backlog.md`.
