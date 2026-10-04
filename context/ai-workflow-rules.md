# Development Workflow

## Approach

Build this project step by step from the feature specs. Context files define what to build, how to build it, and where the work is up to. Always build against these specs. Do not invent behaviour.

## Scoping Rules

- Work on one feature spec at a time, in number order.
- Prefer small changes that can be checked quickly.
- Do not mix unrelated boundaries in one step.

## When To Split Work

Split a step if it combines:

- Database changes and external API calls
- Queue or scheduler changes and UI changes
- More than one external service
- Behaviour that the context files do not define

If a change cannot be tested end to end quickly, the scope is too big. Split it.

## Handling Missing Requirements

- Do not invent product behaviour.
- If something is unclear, settle it in the right context file first.
- If something is missing, add it as an open question in `progress-tracker.md`.
- If scope or the right approach is unclear, ask the user before continuing.

## Asking Before Major Changes

Ask the user first before:

- Adding or removing a Composer or npm dependency.
- Changing or removing starter kit features (auth, teams, settings, passkeys, two-factor).
- Changing the database schema in a way a spec does not describe.
- Anything that contradicts a context file.
- Any git push.

## Protected Foundation Code

Do not modify starter kit code unless a spec or the user asks for it. This includes:

- `app/Actions`, `app/Concerns`, Fortify and team classes, settings controllers.
- `resources/js/components/ui` (shadcn primitives) and the starter kit layouts.

Build sponsor-finder logic in its own classes and pages. Small hook-ups are allowed (for example a sidebar nav item, or a `sponsors()` relation on `Team`).

## Keeping Docs In Sync

Update the relevant context file when you change:

- Architecture or boundaries
- Storage or pipeline states
- Code conventions
- Feature scope

`progress-tracker.md` must show what is actually built, not what is planned.

## Before Moving To The Next Spec

1. The current spec works end to end within its scope.
2. `composer ci:check` passes (frontend check, TS types, Pint, Larastan, Pest).
3. No invariant in `architecture-context.md` was broken.
4. `progress-tracker.md` is updated.
