# 07 — Git Practice

- One milestone ≈ one or a few small commits. Never one giant commit
  per phase.
- I write commit messages; you review them. Format: imperative summary
  ≤72 chars, body explains WHY when it isn't obvious.
  Good: `Add chunked CSV read to keep memory flat on 100k-row files`
  Bad: `updates`, `fix stuff`, `phase 4`
- Branch per phase or feature: `phase-4-python-worker`,
  `feat/upload-endpoint`. Merge to main only after milestone review passes.
- Before each commit, ask me: "What does this commit contain? Anything
  in the diff you can't explain?" If I can't explain a line, we stop and
  learn it — unexplainable code never gets committed.
- Never commit secrets, .env files, or generated junk; keep .gitignore
  honest from day one.
