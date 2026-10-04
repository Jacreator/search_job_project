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

- `app/Console/Commands` - thin entry points: import, enrich (dispatch batch), careers (dispatch batch), status, export.
- `app/Jobs/EnrichSponsor` - one job per sponsor: runs the lookup pipeline for that row.
- `app/Jobs/FindCareersPage` - one job per done sponsor: finds the careers page and hiring signals.
- `app/Services/CompaniesHouse` - all Companies House HTTP calls and name matching.
- `app/Services/WebSearch` - all search API calls. Behind an interface so the provider can change.
- `app/Services/WebsiteScorer` - pure scoring logic. No HTTP.
- `app/Services/WebsiteVerifier` - loads a homepage, checks for the company name, and returns the page text (`VerifiedPage`).
- `app/Services/SharedLookup` - finds a reusable lookup from another sharing team. Database only.
- `app/Services/RatingChange` - applies the status rules for a rating change. Used by import and the dashboard.
- `app/Support` - small pure helpers: `CompanyName` (normalisation), `EmployerClassifier`, `TermMatcher` (shared whole-word matching), `AiDetector`, `SponsorRating`, `PriorityLocations`, `CareersSignals`.
- `app/Enums` - `SponsorStatus`, `TechReason`, `SkipReason`, `VisaSponsorship`.
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
- API keys and the rate limiters are shared across all teams. They live in `.env`, not per team.
- Teams can opt in to sharing finished lookups (`teams.share_lookups`, spec 13). Results are reused only between teams that both opted in.
- `region`, `priority`, `rating_grade`, and `rating_grade_manual` are never shared. Each team gets them from its own import. Rows skipped with `BRating` are never used as a shared source.

## Storage Model

- One `sponsors` table holds the register data, lookup results, careers signals, and pipeline state, keyed by team.
- Raw API responses and page HTML are not stored. Only the fields we use.
- The register CSV is read from `storage/app/imports/` and never committed.
- Exports are written to `storage/app/exports/`.

## Pipeline States

`pending` -> `ch_done` -> `done` or `skipped`
Any step can move to `failed` after the final retry.

| Status    | Meaning                                                          |
| --------- | ---------------------------------------------------------------- |
| pending   | Imported, nothing looked up yet                                  |
| ch_done   | Companies House step finished                                    |
| skipped   | B-rated, no confident match, not active, or not tech (see `skip_reason`) |
| done      | Website step finished (website may still be null)                |
| failed    | Gave up after retries, error saved                               |

Every skipped row has a `skip_reason` (`App\Enums\SkipReason`: `NoMatch`, `NotTech`, `Inactive`, `BRating`).

Rating changes are the only thing that can move a row backwards or sideways, through `RatingChange` (spec 03), from import or from an owner or admin setting a missing grade in the dashboard (spec 12): B moves any row to `skipped` with `BRating`, and A moves a `BRating` row back to `pending`. This is the one exception to invariant 3.

The careers check runs after a row is `done`. It does not change `status`. Its progress is tracked by `careers_checked_at`.

## Classification And Tagging

- `EmployerClassifier` decides tech, in order: SIC code, then name keyword (with no noise keyword), then known employer (full register name, case-insensitive). The result is saved as `tech_reason` (`App\Enums\TechReason`).
- `AiDetector` tags AI companies from the name, then from the homepage text the verifier already fetched.
- `TermMatcher` is the one place term matching happens (AI terms and careers signals): case-insensitive, whole words or phrases, plural "s" allowed, hyphens treated as spaces, curly apostrophes treated as straight.
- `CareersSignals` sets `visa_sponsorship` to `offered` or `not_offered`. Refusal wording beats offer wording. A right to work line counts as not offered only when there is no offer.

## Rating

- The register "Type & Rating" column is parsed into `rating_grade` (`A` or `B`).
- As far as the user knows, B-rated sponsors cannot issue new certificates of sponsorship. So B-rated rows are skipped at import by default. The `skip_b_rated` setting exists so this can be changed if that turns out to be wrong.
- Re-import updates `rating_grade` on existing rows. With `skip_b_rated` on, A to B skips the row (keeping its results), and B to A returns a `BRating` row to `pending`. Rows skipped for other reasons are not touched.
- An unparseable register value never clears a stored grade. Owners and admins can set a missing grade by hand in the dashboard (`rating_grade_manual`). A later register grade replaces a hand-set one.

## Location Priority

- `priority_locations` in config maps a town to a region and a priority. Sheffield is highest.
- Import sets `region` and `priority`. The enrich and careers commands queue the highest priority rows first.

## Rate Limits

- `external-apis`: used by the enrich job. Shared by all teams. Default 30 jobs per minute (`rate_per_minute`).
- `careers-pages`: used by the careers job. Shared by all teams. Default 30 jobs per minute (`careers_rate_per_minute`).
- Companies House allows 600 requests per 5 minutes. Stay well under it.
- Search API calls only happen for active tech sponsors.

## Configuration

All tunable values live in `config/sponsor-finder.php`. Values that change per environment are read from `.env`:

- `COMPANIES_HOUSE_KEY`
- `BRAVE_SEARCH_KEY`
- `SPONSOR_BATCH_SIZE` (default 100)
- `SPONSOR_RATE_PER_MINUTE` (default 30)
- `SPONSOR_CAREERS_RATE_PER_MINUTE` (default 30)
- `SPONSOR_ROUTES` (worker routes to import)
- `SPONSOR_SKIP_B_RATED` (default true)

Lists live in the config file itself: `tech_sic_codes`, `tech_keywords`, `noise_keywords`, `known_employers` (starter list the user checks against the register), `priority_locations`, `ai_keywords`, `careers_link_terms`, `job_board_hosts`, `developer_role_terms`, `visa_offer_terms`, `visa_refusal_terms`, `right_to_work_terms`.

## Invariants

1. Commands never call external APIs directly. They dispatch jobs.
2. Each job handles exactly one sponsor and is safe to run twice.
3. A row never skips a pipeline state. Each step checks the current status first. (Exception: rating changes, see Pipeline States.)
4. Search credits are only spent on active tech sponsors.
5. Scoring, classification, AI detection, rating parsing, priority mapping, and term matching, and careers signal detection have no side effects and are fully unit tested.
6. Tests never hit live APIs.
7. Every sponsor query from a web request is scoped to the current team.
8. Careers checks never call paid APIs. They only fetch the sponsor's own site and the listed job board hosts.
9. Every skipped row has a skip reason.
