# Enrich Job

Wire the services into one queued job per sponsor.

## Job

`App\Jobs\EnrichSponsor`

- `$tries = 3`, `$backoff = [60, 300, 900]`
- Middleware: `RateLimited('external-apis')`
- Increments `attempts` at the start.

## Steps

1. If status is `pending`:
   - Find the company. No match: set `skipped`, stop.
   - Fetch the profile, save number, status, SIC codes, `is_tech`.
   - Set `ch_done`.
2. If not active or not tech: set `skipped`, stop.
3. Search, score, verify. Save website and confidence. Set `done`.

On final failure, set `failed` and save the error message (500 chars max).

## Rate Limiter

Register `external-apis` in `AppServiceProvider` using `config('sponsor-finder.rate_per_minute')`.

## Check When Done

- Feature tests with `Http::fake()` for each path: no match, not tech, inactive, done, failed.
- Running the job twice on a `done` row changes nothing.
