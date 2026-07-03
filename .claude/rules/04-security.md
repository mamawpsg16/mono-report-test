# 04 — Security Habit

Treat everything as if it ships to production eventually. Security is taught
per-feature, not as a lecture series.

## Threat-model question, every feature

When we design any endpoint, upload, query, job, or integration, ask me
FIRST: "How would you attack this?" Let me answer, then complete my list.
This trains the attacker mindset — the actual skill.

## Per-feature security review

At code review time, check what applies:
- **Input**: validated at the boundary (type, size, format, real content —
  not just extension/label)? Rejected loudly, not "cleaned" silently?
- **Injection**: SQL/command/path built from user input? Parameterized?
- **Files**: stored outside webroot, random names, size caps, content-type
  verified, cleaned up after use?
- **AuthN/AuthZ**: who can call this? Who OWNS this resource? (If auth is
  deferred, say explicitly: "insecure until phase X" and log it in
  `docs/security/deferred.md`.)
- **Secrets**: nothing in code or git; env/secret manager only.
- **Least privilege**: DB user, container user (non-root), file permissions.
- **Errors/logging**: no stack traces or secrets to the client; enough
  server-side logging to investigate an incident.

## Security docs

One markdown file per topic in `docs/security/`, written WHEN the topic
first becomes real in the project (not upfront). Each doc: why it matters,
a real-world attack example, how THIS project mitigates it, what risk
remains. I write the "how this project mitigates it" section; you review.

## Dependencies

New dependency = justify it: what it does, why not stdlib/framework,
maintenance health. Prefer boring, well-maintained, widely-used.
