# Progress Tracker

Update this file whenever the current phase, active feature, or implementation state changes.

## Current Phase

- Feature 06 complete. Feature 07 not started.

## Current Goal

- Feature 07: Website scoring

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

- Feature 01: Project setup (2026-10-04):
  - `config/sponsor-finder.php`: API keys, `batch_size` (100), `rate_per_minute` (30), `routes`, `tech_sic_codes` (strings), `tech_keywords`, `noise_keywords`, `known_employers` (30, with the check-the-register note), `priority_locations`.
  - Empty env values fall back to the defaults (`env(...) ?: default`), because `.env.example` lists the keys with no value. `SPONSOR_ROUTES` is a comma-separated list, and empty means the three default routes.
  - `.env.example`: `APP_NAME="Sponsor Finder"` and empty `COMPANIES_HOUSE_KEY`, `BRAVE_SEARCH_KEY`, `SPONSOR_BATCH_SIZE`, `SPONSOR_RATE_PER_MINUTE`.
  - `phpunit.xml` pins all five sponsor env keys to empty, so tests see the defaults and never the real keys.
  - `storage/app/imports` and `storage/app/exports`, each with a `.gitignore` that ignores everything but itself. `storage/app/.gitignore` now un-ignores both folders.
  - `tests/Unit/Config/SponsorFinderConfigTest.php` (6 tests). `composer ci:check` passes (97 tests).
  - `context/*.md` reformatted with `npm run check:fix` (table padding only), so `vp check` passes.

- Feature 02: Sponsors table (2026-10-04):
  - Migration `2026_10_04_000001_create_sponsors_table`: every spec 02 column, `team_id` cascade on delete, `priority` and `status` indexed, unique on `team_id` + `name` + `town`. Runs, rolls back, and re-runs on local MySQL.
  - Enums `SponsorStatus`, `TechReason`, `SkipReason` in `app/Enums`.
  - `App\Models\Sponsor`: `#[Fillable]`, the spec casts, default attributes matching the column defaults, `team()` relation, and `#[Scope]` scopes `pending()`, `readyForLookup()` (pending or ch_done, attempts under `Sponsor::MAX_ATTEMPTS` = 3), `tech()` (`is_tech` true).
  - `Team::sponsors()` has-many added to the starter kit `Team` model (approved by James).
  - `SponsorFactory` (team from `Team::factory()`, pending, A-rated Skilled Worker).
  - `tests/Feature/Sponsors/SponsorModelTest.php` (11 tests): migration up/down, factory, relations, cascade delete, unique per team, enum and other casts, all three scopes. `composer ci:check` passes (108 tests).
  - First commit (`0cefe7a`) was reverted (`95a4e5c`), then rebuilt unchanged on 2026-10-04: the same eight files, checked by blob hash against `0cefe7a`. Migration rolled back and re-run on local MySQL. `composer ci:check` passes (108 tests).

- Context and config changes before Feature 03 (2026-10-04). No Feature 03 code:
  - Closed four open questions: priority locations stay global, route names checked against the 2026-10-02 register, missing towns stored as `''`, Larastan memory limit.
  - `composer.json`: `types:check` runs `phpstan analyse --memory-limit=512M`.
  - `Scale-up` added to the default routes in `config/sponsor-finder.php`, spec 01, `project-overview.md`, and the config test.
  - Spec 03: missing towns stored as `''`; rating parsing cases (A rating, A (Premium), A (SME+), B rating, anything else null, including "UK Expansion Worker: Provisional"); Check When Done now requires a re-import-with-no-town test and one `SponsorRating` unit test per case. Those tests are written in Feature 03, because the importer and `SponsorRating` do not exist yet.
  - `data-model-context.md`: town stored as `''` at import.
  - `SponsorModelTest`: new test that two sponsors with the same name and town `''` in one team are refused. `composer ci:check` passes (109 tests).

- Feature 03: CSV import (2026-10-04):
  - `php artisan sponsors:import {file} --team=` (`app/Console/Commands/ImportSponsors.php`). `{file}` is a path or a file name in `storage/app/imports`. Fails clearly on no `--team`, an unknown team, a missing file, an empty file, or a missing header.
  - `App\Services\RegisterImport`: streams the CSV with `SplFileObject`, maps columns by header, keeps the configured routes, trims and caps cells at 255, stores a missing town as `''`, upserts in chunks of 500 in a transaction, and returns `ImportSummary` (rows read, ignored, new, existing, moved to skipped, moved back to pending). Existing rows get their register fields updated; lookup results are never touched.
  - `App\Support\SponsorRating::grade()`, `App\Support\PriorityLocations::for()` (with `PriorityLocation` value object), `App\Services\RatingChange::apply()` (sets grade and status, does not save; callers handle `rating_grade_manual`), `App\Exceptions\InvalidRegisterFile`.
  - `skip_b_rated` config (`SPONSOR_SKIP_B_RATED`, empty means true), added to `.env.example` and pinned empty in `phpunit.xml`.
  - `Sponsor::$rating_grade` docblock narrowed to `'A'|'B'|null`.
  - Tests: `tests/Feature/Sponsors/ImportSponsorsCommandTest.php` (29), `tests/Unit/Support/SponsorRatingTest.php`, `tests/Unit/Support/PriorityLocationsTest.php`, `tests/Unit/Services/RatingChangeTest.php`, config test for `skip_b_rated`, fixture `tests/Fixtures/register/workers.csv`. `composer ci:check` passes (172 tests).
  - Real register (`storage/app/imports/register-2026-10-02.csv`, downloaded from GOV.UK, git-ignored) into local team `johns-llc`: 143,138 rows read, 8,866 ignored (other routes), 122,938 new sponsors. 87 B-rated skipped with `b_rating`, 647 Sheffield, 3,836 Yorkshire, 9,916 with more than one route. Took 11 s and 64 MB peak. Re-import: 0 new, 122,938 existing, 0 moved, 9 s.

- Register download (2026-10-04, approved by James as an exception to invariant 1):
  - `sponsors:import --team=` with no file downloads the latest register through `App\Services\RegisterDownload` (GOV.UK content API, `register_content_url` in config) into `storage/app/imports/register-YYYY-MM-DD.csv`, reuses it if already there, then imports. Returns a `RegisterFile` value object; failures throw `App\Exceptions\RegisterDownloadFailed`.
  - Only `text/csv` attachments on `https://assets.publishing.service.gov.uk` are used. Streams to a `.part` file, rejects empty or over 50 MB, then renames. A missing named file still fails and never downloads. The team is checked first.
  - `tests/Feature/Sponsors/ImportLatestRegisterTest.php` (9 tests, all HTTP faked, `Sleep::fake()`), fixture `tests/Fixtures/gov-uk/register-content.json`.
  - Live check: downloaded `register-2026-10-02.csv` from GOV.UK (identical to the hand download) and re-imported it (0 new, 122,938 existing). A second run reused the file.
  - Updated `architecture-context.md` (invariant 1 exception, boundary, stack row), `project-overview.md`, spec 03, `security-context.md`.

- Fix: find Scale-up visa sponsors (2026-10-04):
  - `Sponsor::scaleUp()` scope (`route` includes `Sponsor::SCALE_UP_ROUTE`, alone or joined with other routes). No schema change.
  - Tests: two scope tests in `SponsorModelTest` (alone or joined, and combined with grade A), one in `ImportSponsorsCommandTest`.
  - Spec 10 gains a Scale-up column and `--scale-up` filter; spec 12 and `ui-context.md` gain a Scale-up badge and "Scale-up only" filter, built when those specs come up.
  - Local data: 92 Scale-up sponsors in `johns-llc`, all A-rated and pending, 1 in Yorkshire.

- Register download fallback (2026-10-05):
  - When `RegisterDownload` fails for any reason, `sponsors:import` (no file) imports the newest saved `register-YYYY-MM-DD.csv` in `storage/app/imports` (`RegisterDownload::newestSaved()`, by the date in the name). It prints the failure and a warning naming the file, and logs a warning (`reason`, `file`). It fails only when no saved register exists.
  - `ImportLatestRegisterTest`: the five "fails when..." download tests replaced by one fallback test per failure (page error, no CSV, off-host link, CSV error, empty CSV), plus tests for the newest-file choice ignoring undated names and `.part` files, and for failing with no saved register. The tests now use a temporary storage folder, so the real register in `storage/app/imports` never affects them (12 tests).
  - Live check: with a broken content URL, the command warned, imported `register-2026-10-02.csv` (0 new, 122,938 existing), and logged the warning.
  - Spec 03 updated.

- Feature 04: Companies House client (2026-10-05):
  - `App\Services\CompaniesHouse`: `findCompany(string): ?CompanyMatch` (search, 5 results, first whose normalised title equals the normalised name) and `profile(string): CompanyProfile` (number, nullable status, SIC codes). Basic auth with the key as username. Not wired into a job yet.
  - `App\Support\CompanyName::normalise()`: lowercase, cut at "t/a" / "trading as", apostrophes removed, other punctuation to spaces, whole-word removal of limited, ltd, plc, llp, uk, the, group, holdings, spaces collapsed.
  - Value objects `App\Services\CompanyMatch`, `App\Services\CompanyProfile`. Errors throw `App\Exceptions\CompaniesHouseRequestFailed` (missing key, HTTP status, connection, invalid response), with messages free of the key. Two attempts per call; only connection errors, 429, and 5xx are retried.
  - Tests: `tests/Unit/Support/CompanyNameTest.php` (20 cases, including t/a and trading as), `tests/Unit/Services/CompaniesHouseTest.php` (match, no match, empty results, bad items, retries, 401, 404, connection, missing key, profile parsing, URL escaping). Fixtures in `tests/Fixtures/companies-house/`. `composer ci:check` passes.
  - Real names: of 122,938 imported sponsors, 8,775 have a trading name and 2 normalise to nothing (both start with "T/A"), so those 2 will get no match.
  - Live check (2026-10-05, real key): 10 Sheffield sponsors gave 6 matches with status and SIC codes (IDAQ LIMITED has 62030) and 4 no-matches: 3 sole traders not on Companies House, and 1 "t/as" name.
  - Fixes from the live check: `CompanyName` also cuts at "t/as" and "t/a's" (403 register names), and both now match live. "t.a" is not handled (8 names), because it would break names like "I.T.A Tax Accounting". `config/sponsor-finder.php` trims both API keys (the local `.env` had a tab before the key), and `CompaniesHouse` throws `CompaniesHouseRequestFailed::invalidKey()` for a key with control characters instead of letting Guzzle's error escape. Tests added for both.
  - Base URL moved to config (`companies_house_url`, `COMPANIES_HOUSE_URL`, default the live API), added to `.env` and `.env.example`, pinned empty in `phpunit.xml`. Tests for the default, env override, and the client using it.

- Feature 05: Employer classification (2026-10-05):
  - `App\Support\EmployerClassifier::classify(string $name, array $sicCodes): ?TechReason`. In order: a tech SIC code (`Sic`), a whole-word tech keyword in the normalised name with no noise keyword (`Keyword`), the full register name in `known_employers` ignoring case and extra spaces (`KnownEmployer`), else null. Instance class with the four config lists injected through the constructor. Pure.
  - `tests/Unit/Support/EmployerClassifierTest.php` (27 tests): every spec 05 case, plus SIC before name, more noise and whole-word cases, custom lists, and reading config. `composer ci:check` passes.
  - Real names (no SIC codes yet): of 122,938 sponsors, 3,707 are `Keyword` (mostly "technologies", "technology", "tech", "digital", "software") and 20 are `KnownEmployer`.
  - Only 20 of the 30 starter `known_employers` match a register name exactly. The other 10 are spelled differently in the 2026-10-02 register (see Open Questions). Config not changed.

- Markdown indentation (2026-10-06): `.md` files use 2 spaces. `.editorconfig` sets `indent_size = 2` for `*.md`, and `vite.config.ts` adds a `fmt.overrides` entry (`**/*.md`, `tabWidth: 2`). `npm run check:fix` re-indented 7 files (whitespace only). Local `.vscode/settings.json` (git-ignored) sets 2-space tabs for Markdown. Noted in `code-standards.md`.

- Feature 06: Web search (2026-10-06):
  - `App\Contracts\WebSearch::search(string $query): array` (list of `App\Services\SearchResult`, url and title), bound to `App\Services\BraveWebSearch` in `AppServiceProvider`. Not wired into a job yet.
  - `BraveWebSearch`: `GET https://api.search.brave.com/res/v1/web/search` with `X-Subscription-Token`, `q`, `country=gb`, `count=10`, reading `web.results`. Keeps only results with an `http`/`https` URL and a string title. An empty or malformed response gives `[]`.
  - HTTP and connection errors throw `App\Exceptions\WebSearchRequestFailed` (missing key, key with control characters, HTTP status, connection), with messages free of the key and query. Two attempts; only connection errors, 429, and 5xx are retried.
  - `App\Support\SearchQuery::for($name, $town)`: `"{name} {town}"` from the original name, trimmed, name alone when the town is `''` or null.
  - Tests: `tests/Unit/Services/BraveWebSearchTest.php` (binding, parsing, request params and header, empty and malformed responses, bad items, retries, 401/403/422 not retried, connection, missing and bad key), `tests/Unit/Support/SearchQueryTest.php`. Fixtures in `tests/Fixtures/brave/`. `composer ci:check` passes (296 tests).
  - Live check (2026-10-07, real key): "IDAQ LIMITED Sheffield" returned 10 parsed results. All were directory or profile pages (Companies House, LinkedIn, Yell, Cylex, Endole, companycheck, misterwhat), none the company's own site, so spec 07 scoring must rank directories low.

## In Progress

- None.

## Next Up

- Feature 07: Website scoring.

## Open Questions

- `known_employers`: 10 of the 30 starter names do not match the 2026-10-02 register. Register spellings: "BT Group" (British Telecommunications plc), "HSBC Holdings plc" (HSBC UK Bank plc), "NatWest Group PLC" (National Westminster Bank plc), "Jet2.com" (Jet2.com Limited), "Asda Stores Ltd" (Asda Stores Limited), "J Sainsbury Plc" (Sainsbury's Supermarkets Ltd), "Marks and Spencer Group Plc" (Marks and Spencer plc), "Rightmove Group Ltd" (Rightmove plc), "Ernst & Young" (Ernst & Young LLP), "Tata Consultancy Services" (Tata Consultancy Services Limited). Replace them in `config/sponsor-finder.php`?

- Which search provider to use long term if Brave limits are too tight.
- Where will the app be hosted, if anywhere beyond local Herd?

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
- A Companies House API error throws `CompaniesHouseRequestFailed` (retried by the job), never returns null, so errors are not mistaken for no-matches.
- A web search API error throws `WebSearchRequestFailed` the same way. Only an empty or malformed response gives an empty list.
- Database queue, not Redis, to keep setup simple.
- Keep the Laravel React starter kit as the foundation. The dashboard is a React/Inertia page behind login.
- Sponsor data is scoped per team (`team_id` on `sponsors`). API keys and the rate limiters are shared.
- Import, enrich, status, and export stay Artisan commands. Commands take `--team=` (slug).
- Sponsor editing: owners and admins always; members only when an owner or admin turns on their `can_edit_sponsors` flag (default off). New `TeamPermission::ManageSponsorEditors` for owner and admin.
- Lookups are shared between teams only when both teams have `share_lookups` on (default off, owners and admins toggle). `confirmed`, `region`, `priority`, `rating_grade`, and `rating_grade_manual` are never shared, and `BRating` rows are never a source. A copy of another team's hand-confirmed website is capped at confidence 74 and flagged `needs_second_check` until this team confirms it.
- The agent never commits, pushes, or runs any git write command. It may run only `git status`, `git diff`, and `git log`. James does all git writes. Enforced by `.claude/settings.json`.
- Local `APP_URL` is the Herd URL `http://search_job.test`.
- Priority locations stay a single global config list for now. Revisit only if a team outside Yorkshire uses the app.
- Default route names are correct in the register's colon form (`Global Business Mobility: Senior or Specialist Worker`, `Global Business Mobility: Graduate Trainee`), checked against the 2026-10-02 register. `Scale-up` is a fourth default route.
- Import stores a missing town as `''`, never null, so the `team_id` + `name` + `town` unique index catches duplicates (a unique index treats nulls as distinct). The column stays nullable.
- Larastan runs with `--memory-limit=512M` (`types:check` in `composer.json`), because it crashed at the local 128M PHP limit.
- `sponsors:import` with no file downloads the latest register from GOV.UK. This is the one exception to "commands never call external APIs". A named file that is missing still fails.
- Scale-up sponsors are found from `route` with `Sponsor::scaleUp()`, not a separate column.
- A failed register download falls back to the newest saved `register-YYYY-MM-DD.csv`, with a printed and logged warning. The import fails only when no register is saved.
- One sponsor per team + name + town. Register rows for the same name and town are merged: routes joined with `, ` (each once), first non-empty county, B beats A. Name and town match ignoring case and accents, like the MySQL collation.
- Rating grades: "A rating", "A (Premium)" and "A (SME+)" give A. "B rating" gives B. Anything else, including "UK Expansion Worker: Provisional", gives null.

## Session Notes

- Register CSV source: GOV.UK "Register of licensed sponsors: workers".
- Columns: Organisation Name, Town/City, County, Type & Rating, Route.
- `composer dev` runs the server, queue listener, logs, and Vite. The scheduler needs `php artisan schedule:work` separately.
