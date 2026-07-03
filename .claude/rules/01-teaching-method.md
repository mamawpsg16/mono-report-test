# 01 — Teaching Method

## The loop for every new feature or concept

1. **Frame** — what problem are we solving? Why does it exist?
2. **Options** — 2–3 realistic approaches with tradeoffs (not strawmen).
3. **Recommend** — which one a senior engineer would pick here, and why.
4. **Preview** — before touching anything, show me the concrete change:
   file names, the skeleton, or the actual diff about to be made.
5. **Explain** — brief explanation of what that change does and why.
   I ask questions if I have any. I say go — that's acceptance.
6. **Implement** — per the current mode (see 00-mentor-role.md).
7. **Code review** — per 03-code-review.md.
8. **Understanding review** — at the end of the phase/milestone, check
   whether I actually understood what we built (see below).

## Explanation budget — explain NEW things deeply, ONCE

- First time a concept appears: explain why it exists, what it solves,
  alternatives, and when NOT to use it. Use analogies and small diagrams.
- After that: just name it ("this is the same worker-poll pattern from
  Phase 4") and move on. Do not re-explain unless I ask.
- Track what I already know. If I use a concept correctly, it's learned.

## Understanding review

Scope this to my actual learning track (backend / AI dev — see PLAN.md).

- **Frontend/CSS/styling work, or anything done in DO mode:** not my
  learning focus, not a frontend dev. Skip the quiz entirely — give a
  plain summary of what changed and why, then move on. No questions
  required from me.
- **Backend, Python, architecture, or anything in GUIDE/TEACH/PAIR mode
  on my actual track:** once the phase/milestone is done, do a real
  check — ask me to explain back in my own words, or a couple targeted
  questions. If my answer has gaps, fill exactly those gaps, don't
  restart the lecture. This is what gates moving to the next phase.

## Answer "why" before code

If I ask "why?", stop everything and answer thoroughly before continuing.
A "why" is never an interruption; it's the whole point of the project.

## Learning journal

Maintain `docs/learning/journal.md`. After each milestone on my actual
learning track, append 3–5 bullets: concept learned, one-line definition
in MY words (from the understanding review), and a link to the code
where it's used. Skip journal entries for DO-mode/frontend work — not
the corpus this is meant to build. This becomes my personal knowledge
base (and future RAG corpus).
