# Careers Page and Hiring Signals

Find each sponsor's careers page and check it for developer roles and whether it offers or refuses visa sponsorship. Core scope, not a later extra. Builds on `done` rows from spec 08 and the `TermMatcher` from spec 14.

## Migration

`add_careers_fields_to_sponsors_table`:

- `careers_url` (nullable)
- `mentions_developer_roles` (boolean, nullable)
- `visa_sponsorship` (string, nullable, cast to `App\Enums\VisaSponsorship`)
- `careers_checked_at` (timestamp, nullable)

Add casts and fillable entries on `Sponsor`.

## Enum

`App\Enums\VisaSponsorship`: `Offered` (`offered`), `NotOffered` (`not_offered`). Null means no careers page, or the page says nothing either way.

## Config

Add to `config/sponsor-finder.php`:

- `careers_rate_per_minute` (`SPONSOR_CAREERS_RATE_PER_MINUTE`, default 30)
- `careers_link_terms`: `careers, jobs, vacancies, join, work with us, opportunities, open roles, open positions, we're hiring, life at`
- `job_board_hosts`: `greenhouse.io, lever.co, workable.com, ashbyhq.com, teamtailor.com, recruitee.com, bamboohr.com, myworkdayjobs.com, successfactors.com, smartrecruiters.com, pinpointhq.com, personio.com, breezy.hr`
- `developer_role_terms`: `software engineer, software developer, developer, web developer, php developer, laravel developer, full stack, fullstack, backend, back end, frontend, front end, php, laravel, node.js, nodejs, node js, python, typescript, ai engineer, ml engineer, machine learning, machine learning engineer, llm engineer, applied ai, data engineer, data scientist, engineering team`
- `visa_offer_terms`: `visa sponsorship, sponsorship available, able to sponsor, we sponsor, skilled worker, certificate of sponsorship, tier 2`
- `visa_refusal_terms`: `unable to sponsor, cannot sponsor, can't sponsor, no sponsorship, not able to offer sponsorship, unable to offer sponsorship, unable to offer visa sponsorship, does not offer sponsorship, do not offer sponsorship, not offer visa sponsorship`
- `right_to_work_terms`: `right to work in the uk`

Bare "sponsorship" is not a term, because it also matches event or sports sponsorship. Hyphenated forms (for example "full-stack", "back-end") are covered by the `TermMatcher` hyphen rule, so they are not listed twice.

The big employers in `known_employers` mostly use Workday or SuccessFactors, which is why those hosts are listed.

## Job

`App\Jobs\FindCareersPage`

- One sponsor per job. `$maxExceptions = 3`, `$backoff = [60, 300, 900]`, `retryUntil()` one day ahead, as in spec 08 (rate limiter releases must not use up tries).
- Middleware: `RateLimited('careers-pages')`. Register the `careers-pages` limiter in `AppServiceProvider` using `careers_rate_per_minute`.
- Only runs for `done` rows with a website and confidence 50 or more.

## Finding The Page

1. Fetch the homepage. Look for links whose text or href matches a `careers_link_terms` entry, or whose host is (or ends with) a `job_board_hosts` entry.
2. If none work, try the paths `/careers`, `/jobs`, `/join-us`, `/work-with-us` on the sponsor's website.
3. Accept the first candidate that returns 200. Save it as `careers_url`.

## Signals

From the careers page text (stripped, same 200 KB cap as spec 07), using `TermMatcher` (spec 14: case-insensitive, whole words or phrases, plural "s", hyphens as spaces, curly apostrophes as straight):

- `mentions_developer_roles`: true when any `developer_role_terms` entry matches, else false.
- `visa_sponsorship`, checked in this order:
  1. Any `visa_refusal_terms` entry matches: `NotOffered`. A refusal beats an offer, because pages that say both usually carry a standard "we are unable to offer visa sponsorship" disclaimer.
  2. Else any `visa_offer_terms` entry matches: `Offered`.
  3. Else any `right_to_work_terms` entry matches: `NotOffered`.
  4. Else null.

Signal detection lives in a pure helper, `App\Support\CareersSignals`, so it can be unit tested without HTTP.

When no careers page is found: `careers_url` null, `mentions_developer_roles` null, `visa_sponsorship` null.

Always set `careers_checked_at` when the job finishes, found or not.

## Command And Schedule

`php artisan sponsors:careers {--team=} {--limit=}`

- Select eligible rows where `careers_checked_at` is null, ordered by `priority` descending, then `id`.
- Default limit from `batch_size`.
- Dispatch one `FindCareersPage` job per row and print how many were queued.

In `routes/console.php`: `sponsors:careers` hourly, `withoutOverlapping()`.

## Rules

- Follow the outbound request rules in `security-context.md` for every fetch.
- No paid APIs. Only the sponsor's own site and the listed job board hosts.
- The job does not change `status`.

## Dashboard And Export

- Dashboard (spec 12): careers URL link (new tab), a "Developer roles" column, the visa sponsorship badges (see `ui-context.md`), and filters for developer roles and visa sponsorship (offered, not offered, unknown).
- Export (spec 10): "Careers URL", "Developer roles" (Yes / No / blank), and "Visa sponsorship" (Offered / Not offered / blank) columns, and `--developer-roles` and `--visa-sponsorship=offered|not_offered` filters.
- Shared lookups (spec 13): copy the four careers fields.

## Check When Done

- Migration runs and rolls back.
- `Http::fake()` tests: careers link found on the homepage, job board link found (including a `myworkdayjobs.com` subdomain), fallback path found, nothing found.
- New link wording is found ("Open roles", "We’re hiring").
- First 200 response wins. Non-200 candidates are skipped.
- `CareersSignals` unit tests:
  - developer terms found and not found, including plurals ("Software Engineers") and hyphen forms ("Full-Stack")
  - "node.js" and "nodejs" set `mentions_developer_roles`, "a node in the network" does not
  - "phpunit" alone does not set `mentions_developer_roles`
  - "Visa sponsorship available" gives `Offered`
  - "We are unable to offer visa sponsorship" gives `NotOffered`, not `Offered`
  - a page with both an offer and a refusal gives `NotOffered`
  - "You must have the right to work in the UK" alone gives `NotOffered`
  - an offer plus a right to work line gives `Offered`
  - "event sponsorship" alone gives null
  - no visa wording at all gives null
- `careers_checked_at` is set in every outcome, and the command does not queue checked rows again.
- Rows below confidence 50, without a website, or not `done` are never queued.
- Command orders by priority and respects `--team` and `--limit`.
- `php artisan schedule:list` shows the hourly task.
- Dashboard columns and filters, and export columns and filters, work.
- Shared lookups copy the careers fields.
- No test makes a real HTTP request.
