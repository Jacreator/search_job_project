# Architecture Context

## Stack

| Layer            | Technology                                   | Role                                         |
| ---------------- | -------------------------------------------- | -------------------------------------------- |
| Framework        | Laravel 13, PHP 8.3+                         | Commands, jobs, scheduler, web app           |
| Starter kit      | Laravel React starter kit                    | Auth (Fortify), settings, teams, app shell   |
| Frontend         | Inertia v3 + React 19 + TypeScript           | Pages under `resources/js/pages`             |
| Components       | shadcn/ui (already in `resources/js/components/ui`) + Lucide icons | UI primitives          |
| Styling          | Tailwind CSS 4 (Vite)                        | Utility classes, light and dark mode         |
| Routes in TS     | Laravel Wayfinder                            | Typed route helpers for the frontend         |
| Database         | MySQL (local dev), SQLite in-memory (tests)  | Sponsors, lookup results, job state          |
| Queue            | Laravel database queue                       | Background lookups                           |
| Scheduler        | Laravel scheduler                            | Hourly batch dispatch                        |
| Company data     | Companies House REST API (free)              | Company number, status, SIC codes            |
| Web search       | Brave Search API                             | Candidate websites                           |
| Tests            | Pest + `Http::fake()`                        | Unit and feature tests, no live API calls    |
| Quality          | Pint, Larastan (level 7), `tsc`, `vp check`  | Style, static analysis, type checks          |

## System Boundaries

- `app/Console/Commands` - thin entry points: import, enrich (dispatch batch), status, export.
- `app/Jobs` - one job per sponsor: runs the lookup pipeline for that row.
- `app/Services/CompaniesHouse` - all Companies House HTTP calls and name matching.
- `app/Services/WebSearch` - all search API calls. Behind an interface so the provider can change.
- `app/Services/WebsiteScorer` - pure scoring logic. No HTTP.
- `app/Services/WebsiteVerifier` - loads a homepage and checks for the company name.
- `app/Support` - small helpers such as name normalisation and the tech SIC list.
- `app/Http/Controllers/Sponsors` + `app/Http/Requests/Sponsors` - review dashboard only.
- `resources/js/pages/sponsors` - review dashboard pages.
- `routes/web.php` - dashboard routes, inside the existing `{current_team}` group.
- `routes/console.php` - schedule definitions.
- Starter kit areas (`app/Actions`, `app/Concerns`, `Fortify`, `Teams`, `Settings`, `resources/js/components`, `resources/js/layouts`) - foundation code. Do not change unless a spec says so.

## Team Scoping

- Every sponsor row belongs to one team (`team_id`).
- Web routes live under `/{current_team}/...` and use the existing `EnsureTeamMembership` middleware.
- Controllers only load sponsors for the current team. A sponsor from another team returns 404.
- Commands take a `--team=` option (team slug) where they read or write one team's data.
- API keys and the rate limiter are shared across all teams. They live in `.env`, not per team.
- Teams can opt in to sharing finished lookups (`teams.share_lookups`, spec 13). Results are reused only between teams that both opted in.

## Storage Model

- One `sponsors` table holds the register data, lookup results, and pipeline state, keyed by team.
- Raw API responses are not stored. Only the fields we use.
- The register CSV is read from `storage/app/imports/` and never committed.
- Exports are written to `storage/app/exports/`.

## Pipeline States

`pending` -> `ch_done` -> `done` or `skipped`
Any step can move to `failed` after the final retry.

| Status    | Meaning                                                |
| --------- | ------------------------------------------------------ |
| pending   | Imported, nothing looked up yet                        |
| ch_done   | Companies House step finished                          |
| skipped   | No confident match, not tech, or not active            |
| done      | Website step finished (website may still be null)      |
| failed    | Gave up after retries, error saved                     |

## Rate Limits

- One named limiter, `external-apis`, used by the enrich job. Shared by all teams.
- Default 30 jobs per minute. Configurable in `config/sponsor-finder.php`.
- Companies House allows 600 requests per 5 minutes. Stay well under it.
- Search API calls only happen for active tech sponsors.

## Configuration

All tunable values live in `config/sponsor-finder.php`, read from `.env`:

- `COMPANIES_HOUSE_KEY`
- `BRAVE_SEARCH_KEY`
- `SPONSOR_BATCH_SIZE` (default 100)
- `SPONSOR_RATE_PER_MINUTE` (default 30)
- `SPONSOR_ROUTES` (worker routes to import)

## Invariants

1. Commands never call external APIs directly. They dispatch jobs.
2. Each job handles exactly one sponsor and is safe to run twice.
3. A row never skips a pipeline state. Each step checks the current status first.
4. Search credits are only spent on active tech sponsors.
5. Scoring logic has no side effects and is fully unit tested.
6. Tests never hit live APIs.
7. Every sponsor query from a web request is scoped to the current team.
