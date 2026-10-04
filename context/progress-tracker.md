# Progress Tracker

Update this file whenever the current phase, active feature, or implementation state changes.

## Current Phase

- Context setup complete. Feature 01 not started.

## Current Goal

- Feature 01: Project setup (sponsor finder config and storage folders on top of the starter kit)

## Completed

- Baseline: Laravel 13 React starter kit installed (Inertia + React + TypeScript, Fortify auth, teams, passkeys, two-factor, Pest, Pint, Larastan level 7). 91 tests pass, Pint passes. Local MySQL database `laravel_search_job` migrated.
- Context setup (2026-10-04):
  - Extracted the sponsor finder context pack. `AGENTS.md` and `CLAUDE.md` at the repo root, docs in `context/`, specs in `context/feature-specs/`.
  - Added `data-model-context.md`, `testing-context.md`, `security-context.md`, `github-workflow-context.md`, `deployment-context.md` (sedatus used as a template only).
  - Adapted the docs to the starter kit: dashboard is a React page behind login, sponsor data is scoped per team, specs 01, 02, 03, 09, 10, and the dashboard spec updated for `team_id` and `--team`.
  - `.gitignore`: stopped ignoring `/AGENTS.md` and `/CLAUDE.md`, added `/context/current-issues.md`.
  - Added spec 11 (sponsor edit permission); review dashboard is now spec 12.
  - Added spec 13 (shared lookups between teams); careers page is now spec 14.
- `APP_URL` set to `http://search_job.test` in `.env` and `.env.example`. `phpunit.xml` pins `APP_URL=http://localhost` so tests do not depend on `.env`. 91 tests still pass.

## In Progress

- None.

## Next Up

- Feature 01: Project setup.

## Open Questions

- Which search provider to use long term if Brave limits are too tight.
- Whether to add a careers page finder after the main pipeline works.
- Where will the app be hosted, if anywhere beyond local Herd?
- Git repo not initialised yet. Initialise when the user says so (no pushing).

## Architecture Decisions

- Tech status comes from Companies House SIC codes, not from company names.
- No confident Companies House match means the row is skipped, not guessed.
- Database queue, not Redis, to keep setup simple.
- Keep the Laravel React starter kit as the foundation. The dashboard is a React/Inertia page behind login.
- Sponsor data is scoped per team (`team_id` on `sponsors`). API keys and the rate limiter are shared.
- Import, enrich, status, and export stay Artisan commands. Commands take `--team=` (slug).
- Sponsor editing: owners and admins always; members only when an owner or admin turns on their `can_edit_sponsors` flag (default off). New `TeamPermission::ManageSponsorEditors` for owner and admin.
- Lookups are shared between teams only when both teams have `share_lookups` on (default off, owners and admins toggle). `confirmed` is never shared. A copy of another team's hand-confirmed website is capped at confidence 74 and flagged `needs_second_check` until this team confirms it.
- Local `APP_URL` is the Herd URL `http://search_job.test`.

## Session Notes

- Register CSV source: GOV.UK "Register of licensed sponsors: workers".
- Columns: Organisation Name, Town/City, County, Type & Rating, Route.
- `composer dev` runs the server, queue listener, logs, and Vite. The scheduler needs `php artisan schedule:work` separately.
