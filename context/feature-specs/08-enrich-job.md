# Enrich Job

Wire the services into one queued job per sponsor.

## Job

`App\Jobs\EnrichSponsor`

- `$tries = 3`, `$backoff = [60, 300, 900]`
- Middleware: `RateLimited('external-apis')`
- Increments `attempts` at the start.

## Steps

1. If status is `pending`:
   - Find the company. No match: set `skipped` with skip reason `NoMatch`, stop.
   - Fetch the profile, save number, status, and SIC codes.
   - Classify with `EmployerClassifier::classify($name, $sicCodes)` (spec 05). Save `tech_reason`, and set `is_tech` to true when it is not null, else false.
   - Set `ch_done`.
2. If the company is not active: set `skipped` with skip reason `Inactive`, stop.
3. If not tech: set `skipped` with skip reason `NotTech`, stop.
4. Search, score, verify. Save website and confidence. Set `done`.

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
