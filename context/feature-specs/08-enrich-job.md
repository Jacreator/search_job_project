# Enrich Job

Wire the services into one queued job per sponsor.

## Job

`App\Jobs\EnrichSponsor`

- `$maxExceptions = 3`, `$backoff = [60, 300, 900]`, `retryUntil()` one day ahead. No `$tries`: a job released by the rate limiter counts as a try, so with `$tries = 3` a batch bigger than the limit would fail rows that never ran (changed 2026-10-07, approved by James). A row now fails only after 3 real errors.
- `$deleteWhenMissingModels = true`, so a deleted sponsor's job is dropped.
- `ShouldBeUnique`, keyed by sponsor id, `$uniqueFor = 86400` (the same day as `retryUntil()`). A sponsor whose job is still queued or waiting on a retry is not queued again by the next batch, so no second attempt or search credit is spent on it. The lock is freed when the job finishes or fails (added 2026-10-09, approved by James).
- Middleware: `RateLimited('external-apis')`
- Increments `attempts` at the start, after checking the status (a `done`, `skipped`, or `failed` row is left alone, attempts included).

## Steps

1. If status is `pending`:
   - Find the company. No match: set `skipped` with skip reason `NoMatch`, stop.
   - Fetch the profile, save number, status, and SIC codes.
   - Classify with `EmployerClassifier::classify($name, $sicCodes)` (spec 05). Save `tech_reason`, and set `is_tech` to true when it is not null, else false.
   - Set `ch_done`.
2. If the company is not active (`company_status` is not `active`, including null): set `skipped` with skip reason `Inactive`, stop.
3. If not tech: set `skipped` with skip reason `NotTech`, stop.
4. Search (`SearchQuery::for($name, $town)`), score, verify. Save website and confidence (`WebsiteScorer::confidence`), both null when no result scores. Set `done`.

Every skip path sets `skip_reason`. Any other status leaves it null.

On final failure, set `failed` and save the error message (500 chars max).

Spec 13 adds a shared lookup check before step 1. Spec 14 adds AI tagging after classification and after verification.

## Rate Limiter

Register `external-apis` in `AppServiceProvider` using `config('sponsor-finder.rate_per_minute')`.

## Check When Done

- Feature tests with `Http::fake()` for each path: no match, not tech, inactive, done, failed.
- Each skip path saves the right `skip_reason`.
- A sponsor tagged by keyword (no tech SIC code) reaches `done`.
- Running the job twice on a `done` row changes nothing.
