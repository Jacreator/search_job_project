# Progress Tracker

Update this file whenever the current phase, active feature, or implementation state changes.

## Current Phase

- Feature 01 (project setup) built. Waiting on two `composer ci:check` blockers that came before this spec (see In Progress).

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

- Context change, four fixes (2026-10-04). Docs only, no code:
  - Spec 01: `known_employers` starter list of 30 employers, with a note to check names against the register. "property" added to noise keywords.
  - Spec 03: rating changes on re-import (A to B, B to A), with summary counts and tests. Closes the open question.
  - Spec 05: tests for "SKY UK LIMITED" and "Acme Property Developer Ltd".
  - Spec 13: stopped copying `region`, `priority`, and `rating_grade`. `BRating` rows are never a source.
  - Spec 15: visa terms narrowed (no bare "sponsorship"), "node" replaced by "node.js", with tests.
  - Updated `architecture-context.md`, `data-model-context.md`, and `testing-context.md` to match.

- Context change, follow-up answers (2026-10-04). Docs only, no code:
  - Missing rating grades: owners and admins set them in the dashboard (spec 12, new `rating_grade_manual` column in spec 02). Import never clears a grade, and a register grade replaces a hand-set one. Shared `RatingChange` service (spec 03).
  - Careers signals use whole-word and whole-phrase matching (spec 15).
  - Known employers match the full register name, case-insensitive (specs 01 and 05).
  - Updated `architecture-context.md`, `data-model-context.md`, `security-context.md`, `ui-context.md`, `testing-context.md`, and spec 13.

- Context change, job-ad wording (2026-10-04). Docs only, no code:
  - New `TermMatcher` (spec 14): whole words or phrases, plural "s", hyphens as spaces, curly apostrophes as straight. Used by `AiDetector` and `CareersSignals`.
  - Spec 15: `mentions_visa_sponsorship` replaced by `visa_sponsorship` (`offered`, `not_offered`, null) with offer, refusal, and right to work term lists. Refusal beats offer.
  - Spec 15: more role titles, careers link wording, and job board hosts (Workday, SuccessFactors, SmartRecruiters, Pinpoint, Personio, Breezy).
  - Spec 14: more AI terms (genai, large language model, mlops, ai-powered).
  - Updated specs 10, 12, 13 and `architecture`, `data-model`, `ui`, `testing`, `code-standards`, `project-overview`.

- Git rule (2026-10-04): the agent never commits or pushes. Added `.claude/settings.json` with deny rules for git write commands. Updated `AGENTS.md`, `ai-workflow-rules.md`, and `github-workflow-context.md`.

## In Progress

- Feature 01: Project setup (2026-10-04). Built:
  - `config/sponsor-finder.php`: API keys, `batch_size` (100), `rate_per_minute` (30), `routes`, `tech_sic_codes` (strings), `tech_keywords`, `noise_keywords`, `known_employers` (30, with the check-the-register note), `priority_locations`.
  - Empty env values fall back to the defaults (`env(...) ?: default`), because `.env.example` lists the keys with no value. `SPONSOR_ROUTES` is a comma-separated list, and empty means the three default routes.
  - `.env.example`: `APP_NAME="Sponsor Finder"` and empty `COMPANIES_HOUSE_KEY`, `BRAVE_SEARCH_KEY`, `SPONSOR_BATCH_SIZE`, `SPONSOR_RATE_PER_MINUTE`.
  - `phpunit.xml` pins all five sponsor env keys to empty, so tests see the defaults and never the real keys.
  - `storage/app/imports` and `storage/app/exports`, each with a `.gitignore` that ignores everything but itself. `storage/app/.gitignore` now un-ignores both folders.
  - `tests/Unit/Config/SponsorFinderConfigTest.php` (6 tests). Pint, Larastan (with `--memory-limit=1G`), `tsc`, and Pest (97 tests) pass.
  - Blockers for `composer ci:check`, both from before this spec: (1) `vp check` reports formatting issues in 10 committed `context/*.md` files; (2) Larastan crashes at the local PHP 128M memory limit.

## Next Up

- Clear the two `ci:check` blockers, then Feature 02: Sponsors table.

## Open Questions

- Which search provider to use long term if Brave limits are too tight.
- Should priority locations become a per-team setting later?
- Where will the app be hosted, if anywhere beyond local Herd?
- Default route names use the register's colon form (`Global Business Mobility: Senior or Specialist Worker`, `Global Business Mobility: Graduate Trainee`). Check them against the latest register CSV before spec 03.
- `vp check` formats `context/*.md`: reformat the docs with `vp check --fix`, or exclude `context/` from the formatter?
- Larastan needs more than 128M: set `memory_limit` in local php.ini, or add `--memory-limit` to the `types:check` script?

## Architecture Decisions

- Tech status comes from `EmployerClassifier`: SIC code first, then name keyword with no noise keyword, then known employer. The reason is saved as `tech_reason`.
- AI tagging uses the name, then the homepage text the verifier already fetched. No extra HTTP call.
- Sponsors get a region and priority from their town at import. Sheffield is highest. Enrich and careers queues run highest priority first.
- B-rated sponsors are skipped at import by default (`skip_b_rated`), because as far as the user knows they cannot issue new certificates of sponsorship.
- Re-import updates `rating_grade`. With `skip_b_rated` on: A to B sets `skipped` with `BRating` whatever the status, keeping results, website, and `confirmed`. B to A on a `BRating` row sets `pending` and clears `skip_reason`, keeping results. Rows skipped for other reasons are not changed. The import summary reports both counts.
- `known_employers` ships with a starter list of 30 large UK employers. The user checks each name against the latest register CSV and can add more. Matching uses the full register name, ignoring only case and extra spaces.
- Missing rating grades are set by owners or admins in the dashboard. Import never clears a stored grade. A register grade always replaces a hand-set one. All grade changes go through `RatingChange`.
- Careers signal and AI term matching goes through `TermMatcher`: whole word or phrase, case-insensitive, plural "s", hyphens as spaces.
- Visa sponsorship is three-state (`offered`, `not_offered`, null). Refusal wording beats offer wording, and a right to work line alone counts as not offered.
- Visa terms exclude bare "sponsorship". Developer role terms use "node.js", not "node". "property" is a noise keyword.
- Every skipped row has a `skip_reason`.
- The careers page finder is core scope (spec 15). It runs after `done`, has its own `careers-pages` limiter, and never calls paid APIs.
- No confident Companies House match means the row is skipped, not guessed.
- Database queue, not Redis, to keep setup simple.
- Keep the Laravel React starter kit as the foundation. The dashboard is a React/Inertia page behind login.
- Sponsor data is scoped per team (`team_id` on `sponsors`). API keys and the rate limiters are shared.
- Import, enrich, status, and export stay Artisan commands. Commands take `--team=` (slug).
- Sponsor editing: owners and admins always; members only when an owner or admin turns on their `can_edit_sponsors` flag (default off). New `TeamPermission::ManageSponsorEditors` for owner and admin.
- Lookups are shared between teams only when both teams have `share_lookups` on (default off, owners and admins toggle). `confirmed`, `region`, `priority`, `rating_grade`, and `rating_grade_manual` are never shared, and `BRating` rows are never a source. A copy of another team's hand-confirmed website is capped at confidence 74 and flagged `needs_second_check` until this team confirms it.
- The agent never commits, pushes, or runs any git write command. It may run only `git status`, `git diff`, and `git log`. James does all git writes. Enforced by `.claude/settings.json`.
- Local `APP_URL` is the Herd URL `http://search_job.test`.

## Session Notes

- Register CSV source: GOV.UK "Register of licensed sponsors: workers".
- Columns: Organisation Name, Town/City, County, Type & Rating, Route.
- `composer dev` runs the server, queue listener, logs, and Vite. The scheduler needs `php artisan schedule:work` separately.
