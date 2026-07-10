# 0003 — In-app confirm dialog & toast, not SweetAlert2

## Context

The admin screens needed two cross-cutting UI primitives: a **confirmation
dialog** before consequential saves (with an in-dialog loading state while the
request runs), and **toast notifications** for success/error feedback. These are
needed in more than one place (Users now; Roles, Rewards later), so the ask was
explicitly for something reusable "dynamically" from any component — not a
one-off modal per screen.

## Problem

Build these from scratch on the existing design system, or pull in a
batteries-included library (SweetAlert2 was named)? The project already has an
`AppModal`, a full set of CSS design tokens, and per-button spinners — so most of
the pieces exist. Against that: a library is faster to feature-rich dialogs but
ships its own look and weight.

## Options

1. **SweetAlert2 + a thin wrapper** — `npm i sweetalert2`, wrap `Swal.fire` in a
   helper so call sites stay clean. Pro: instant rich features (icons, timers,
   `html`, queued dialogs). Con: ~50KB dependency for something 90% already
   present; its default styling doesn't match the app's tokens (colors, radii,
   fonts) without fighting its theme layer; another third-party surface to keep
   updated.
2. **Home-grown `useConfirm` + `useToast` composables** — module-scoped
   reactive state (one dialog, one toast stack) rendered by single host
   components (`ConfirmModal`, `ToastHost`) mounted once in `App.vue`. Pro: zero
   new dependency, matches the design system exactly, imperative promise-based
   API like Swal's (`const ok = await confirm({...})`), supports an async
   `onConfirm` with in-dialog spinner + server-error surfacing. Con: we maintain
   it; fewer features than a mature library out of the box.
3. **Just standardize loading, skip confirms** — extract only the
   button-loading + toast pattern, add confirms later. Rejected: the destructive
   action that actually needs a confirm (last-admin role removal) exists *now*.

## Decision

**Option 2 — home-grown composables.** The deciding factors: (a) the project's
own dependency rule (justify every dep; prefer stdlib/framework/existing over a
new library), (b) design-system consistency — a Swal dialog would look visibly
foreign next to the hand-styled modals, and (c) the pieces already existed, so
the marginal cost of building was low. The API deliberately mirrors SweetAlert2
(`confirm({ title, text, html, danger, onConfirm })` returning a promise) so the
ergonomics people expect from Swal are kept without the dependency.

Shape:

- `useConfirm.js` / `useToast.js` — module-scoped state + the imperative API.
- `<ConfirmModal>` (z-index 700, above `AppModal`'s 500) and `<ToastHost>`
  (z-index 900) mounted once in `App.vue`. Callers never mount UI; they call a
  function. See ADR body of `App.vue`, and `docs/learning/journal.md`.

## Consequences

- **We own the behavior.** Features SweetAlert2 gives free (input dialogs,
  dialog queue, timers-with-progress-bar) are ours to add if ever needed.
- **`html` uses `v-html`** — an XSS footgun by construction. Mitigated by
  contract: `html` is documented as trusted-markup-only; dynamic/user values go
  through `text` (auto-escaped). No sanitizer is bundled, so the contract is the
  only guard.
- **Single-instance assumption is load-bearing.** Because state is module-scoped,
  exactly one `<ConfirmModal>`/`<ToastHost>` may be mounted; a second would bind
  to the same state and double-render. Enforced by convention (mount only in
  `App.vue`), not by code.
- **No queueing.** A second `confirm()` while one is open overwrites the first's
  resolver — the earlier promise would never settle. Fine for the current UI
  (one dialog at a time); would need a queue if that assumption breaks.
- Confirms currently gate *every* save in Users, including non-destructive
  detail edits. Whether to reserve confirms for destructive actions only is an
  open UX call (`docs/backlog.md`).

## Revisit when

- We need dialog features that are expensive to hand-roll (multi-step wizards,
  input prompts, dialog queueing) — re-evaluate a library at that point.
- A second host instance or concurrent `confirm()` calls become a real pattern —
  add a queue and/or an explicit single-mount guard.
- The `v-html` contract proves too risky in practice (someone passes user data) —
  add a sanitizer (e.g. DOMPurify) or drop `html` for a slot-based body.
