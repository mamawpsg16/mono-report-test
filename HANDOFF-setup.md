# HANDOFF — Mentor Kit Setup

You are Claude Code. The user has downloaded a folder called `mentor-kit/`
and placed it at the root of their project. Your job is to set it up correctly.

**Do not start Phase 0 yet. Setup first, then stop and confirm.**

---

## What's in mentor-kit/

```
mentor-kit/
  README.md           ← explains the kit (for the human, not you)
  CLAUDE.md           ← template to place at project root
  PLAN-notes.md       ← suggested improvements to PLAN.md (for the human)
  rules/
    00-mentor-role.md
    01-teaching-method.md
    02-workflow.md
    03-code-review.md
    04-security.md
    05-documentation.md
    06-testing.md
    07-git.md
```

---

## Step 1 — Create the folder structure

Run these commands from the project root:

```bash
mkdir -p .claude/rules
mkdir -p docs/adr
mkdir -p docs/architecture
mkdir -p docs/security
mkdir -p docs/learning
touch docs/backlog.md
touch docs/learning/journal.md
touch docs/security/deferred.md
```

---

## Step 2 — Move the rule files

```bash
cp mentor-kit/rules/*.md .claude/rules/
```

Claude Code auto-loads every `.md` file in `.claude/rules/` as project
memory. No imports needed for those — they load automatically.

---

## Step 3 — Place CLAUDE.md at the project root

```bash
cp mentor-kit/CLAUDE.md ./CLAUDE.md
```

Then open `CLAUDE.md` and fill in the two sections that have placeholders:

**"Current state"** — update to reflect where the project actually is:
- Current phase (e.g., `0 — not started`)
- Current milestone
- Default mode (`GUIDE` for learning, adjust for work)
- Any open questions

**"Project-specific facts"** — replace the dataforge defaults if this is
a different project:
- Your actual stack
- Key architectural decisions already locked
- How to run the project locally
- Any deferred security items

---

## Step 4 — Keep PLAN.md at the project root (or create it)

If the user already has a plan doc (e.g., the dataforge build plan),
rename or copy it to `PLAN.md` at the project root:

```bash
cp <their-existing-plan>.md ./PLAN.md
```

CLAUDE.md imports it via `@PLAN.md`. If there is no plan yet, create a
blank `PLAN.md` and note that it needs filling in.

---

## Step 5 — Clean up

```bash
rm -rf mentor-kit/
```

The kit is now installed. The source folder is no longer needed.

---

## Step 6 — Verify the structure

Confirm the project root looks like this:

```
your-project/
  CLAUDE.md               ← mentor rules + project facts
  PLAN.md                 ← build plan + decisions
  .claude/
    rules/
      00-mentor-role.md
      01-teaching-method.md
      02-workflow.md
      03-code-review.md
      04-security.md
      05-documentation.md
      06-testing.md
      07-git.md
  docs/
    adr/                  ← decision records go here
    architecture/         ← diagrams go here
    security/
      deferred.md         ← tracks deferred security items
    learning/
      journal.md          ← learning log (concepts in user's own words)
    backlog.md            ← parked ideas from reviews
```

---

## Step 7 — Tell the user what was done

Give the user a short summary:
- What folders were created
- What files were moved where
- The two sections in CLAUDE.md they need to fill in
- That PLAN-notes.md in the original download has suggestions for
  improving their project plan (they can read it separately)

Then say: **"Setup complete. Fill in CLAUDE.md sections marked above,
then tell me which phase and milestone to start on."**

Do not begin any development work until the user confirms CLAUDE.md is
filled in and gives the go-ahead.

---

## For future projects (reuse instructions)

If the user wants to reuse this kit in a new project later:

1. Copy `.claude/rules/` from any previous project — rules are
   stack-agnostic, nothing to change.
2. Write a new `PLAN.md` for the new project.
3. Copy `CLAUDE.md`, rewrite only "Current state" and
   "Project-specific facts".
4. Re-run Step 1 (mkdir docs structure).
5. Start Claude Code and say: "Read CLAUDE.md. We're in [MODE] mode,
   Phase [N], milestone [M]."

Alternatively, keep the rules in a home folder once and import them
from every project's CLAUDE.md using absolute paths:

```md
@~/.claude/shared-rules/00-mentor-role.md
@~/.claude/shared-rules/01-teaching-method.md
... etc
```

This way you maintain one copy of the rules, updated once, used
everywhere.
