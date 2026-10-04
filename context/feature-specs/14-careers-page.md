# Careers Page Finder (later)

Only start this after features 01 to 13 are done.

## Idea

For `done` rows with confidence 75 or more, look for a careers page.

- Try common paths: `/careers`, `/jobs`, `/join-us`, `/work-with-us`.
- Accept the first that returns 200 and mentions "job", "career" or "vacanc".
- Save to a new `careers_url` column.
- Run as its own job with its own rate limit.

## Check When Done

- `Http::fake()` tests for found and not found.
- Export includes the careers URL column.
