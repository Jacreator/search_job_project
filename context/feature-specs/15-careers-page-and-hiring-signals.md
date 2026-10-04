# Careers Page and Hiring Signals

Find each sponsor's careers page and check it for developer roles and visa sponsorship. Core scope, not a later extra. Builds on `done` rows from spec 08.

## Migration

`add_careers_fields_to_sponsors_table`:

- `careers_url` (nullable)
- `mentions_developer_roles` (boolean, nullable)
- `mentions_visa_sponsorship` (boolean, nullable)
- `careers_checked_at` (timestamp, nullable)

Add casts and fillable entries on `Sponsor`.

## Config

Add to `config/sponsor-finder.php`:

- `careers_rate_per_minute` (`SPONSOR_CAREERS_RATE_PER_MINUTE`, default 30)
- `job_board_hosts`: `greenhouse.io, lever.co, workable.com, ashbyhq.com, teamtailor.com, recruitee.com, bamboohr.com`
- `developer_role_terms`: `software engineer, developer, backend, back-end, full stack, php, laravel, node, ai engineer, machine learning engineer, data engineer`
- `visa_terms`: `visa sponsorship, sponsorship, skilled worker, certificate of sponsorship`

## Job

`App\Jobs\FindCareersPage`

- One sponsor per job. `$tries = 3`, `$backoff = [60, 300, 900]`.
- Middleware: `RateLimited('careers-pages')`. Register the `careers-pages` limiter in `AppServiceProvider` using `careers_rate_per_minute`.
- Only runs for `done` rows with a website and confidence 50 or more.

## Finding The Page

1. Fetch the homepage. Look for links whose text or href contains careers, jobs, vacancies, join, or work with us, or whose host is in `job_board_hosts`.
2. If none work, try the paths `/careers`, `/jobs`, `/join-us`, `/work-with-us` on the sponsor's website.
3. Accept the first candidate that returns 200. Save it as `careers_url`.

## Signals

From the careers page text (stripped and lowercased, same 200 KB cap as spec 07):

- `mentions_developer_roles`: true when any `developer_role_terms` entry appears.
- `mentions_visa_sponsorship`: true when any `visa_terms` entry appears.

Signal detection lives in a pure helper, `App\Support\CareersSignals`, so it can be unit tested without HTTP.

When no careers page is found: `careers_url` null, both signals null.

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

- Dashboard (spec 12): careers URL link (new tab), "Developer roles" and "Visa sponsorship mentioned" columns or badges, and filters for both.
- Export (spec 10): "Careers URL", "Developer roles", and "Visa sponsorship" columns, and `--developer-roles` and `--visa-sponsorship` filters.
- Shared lookups (spec 13): copy the four careers fields.

## Check When Done

- Migration runs and rolls back.
- `Http::fake()` tests: careers link found on the homepage, job board link found, fallback path found, nothing found.
- First 200 response wins. Non-200 candidates are skipped.
- Signals: developer terms and visa terms detected, and not detected, in unit tests for `CareersSignals`.
- `careers_checked_at` is set in every outcome, and the command does not queue checked rows again.
- Rows below confidence 50, without a website, or not `done` are never queued.
- Command orders by priority and respects `--team` and `--limit`.
- `php artisan schedule:list` shows the hourly task.
- Dashboard columns and filters, and export columns and filters, work.
- Shared lookups copy the careers fields.
- No test makes a real HTTP request.
