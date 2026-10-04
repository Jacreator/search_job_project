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
  - Added spec 13 (shared lookups between teams); careers page was moved to spec 14 (since replaced by spec 15).
- `APP_URL` set to `http://search_job.test` in `.env` and `.env.example`. `phpunit.xml` pins `APP_URL=http://localhost` so tests do not depend on `.env`. 91 tests still pass.
- Context change, closing five gaps (2026-10-04). Docs only, no code:
  - Spec 05 rewritten as Employer Classification (`EmployerClassifier`, `TechReason`). File renamed to `05-employer-classification.md`. The old SIC-only classifier is removed from every doc.
  - New spec 14 AI Tagging (`AiDetector`, `is_ai`, `ai_source`). Spec 07 verifier now returns page text (`VerifiedPage`, 200 KB cap).
  - Location priority: `priority_locations` config, `region` and `priority` columns, enrich queue ordered by priority, region filters.
  - Rating: `rating_grade`, `skip_reason`, `SkipReason` enum, B-rated rows skipped at import by default (`skip_b_rated`).
  - Old spec 14 (careers page, later) replaced by spec 15 Careers Page and Hiring Signals, now core scope, with its own job, limiter, command, and schedule.
  - Spec 13 now also copies the new fields. Updated specs 01, 02, 03, 07, 08, 09, 10, 12, 13 and `project-overview`, `architecture`, `data-model`, `ui`, `testing`, `security`, `code-standards`, and `AGENTS.md`.

## In Progress

- None.

## Next Up

- Feature 01: Project setup.

## Open Questions

- Which search provider to use long term if Brave limits are too tight.
- Should priority locations become a per-team setting later?
- On re-import, should a row whose rating changes be updated (for example an A row that becomes B moves to skipped, or a B row that becomes A goes back to pending)? Until decided, spec 03 only applies the B-rated skip to new rows.
- Where will the app be hosted, if anywhere beyond local Herd?
- Git repo not initialised yet. Initialise when the user says so (no pushing).

## Architecture Decisions

- Tech status comes from `EmployerClassifier`: SIC code first, then name keyword with no noise keyword, then known employer. The reason is saved as `tech_reason`.
- AI tagging uses the name, then the homepage text the verifier already fetched. No extra HTTP call.
- Sponsors get a region and priority from their town at import. Sheffield is highest. Enrich and careers queues run highest priority first.
- B-rated sponsors are skipped at import by default (`skip_b_rated`), because as far as the user knows they cannot issue new certificates of sponsorship.
- Every skipped row has a `skip_reason`.
- The careers page finder is core scope (spec 15). It runs after `done`, has its own `careers-pages` limiter, and never calls paid APIs.
- No confident Companies House match means the row is skipped, not guessed.
- Database queue, not Redis, to keep setup simple.
- Keep the Laravel React starter kit as the foundation. The dashboard is a React/Inertia page behind login.
- Sponsor data is scoped per team (`team_id` on `sponsors`). API keys and the rate limiters are shared.
- Import, enrich, status, and export stay Artisan commands. Commands take `--team=` (slug).
- Sponsor editing: owners and admins always; members only when an owner or admin turns on their `can_edit_sponsors` flag (default off). New `TeamPermission::ManageSponsorEditors` for owner and admin.
- Lookups are shared between teams only when both teams have `share_lookups` on (default off, owners and admins toggle). `confirmed` is never shared. A copy of another team's hand-confirmed website is capped at confidence 74 and flagged `needs_second_check` until this team confirms it.
- Local `APP_URL` is the Herd URL `http://search_job.test`.

## Session Notes

- Register CSV source: GOV.UK "Register of licensed sponsors: workers".
- Columns: Organisation Name, Town/City, County, Type & Rating, Route.
- `composer dev` runs the server, queue listener, logs, and Vite. The scheduler needs `php artisan schedule:work` separately.
