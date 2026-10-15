# Careers Re-checks And Retry Later

**Status: draft, awaiting James's approval.**

Check careers pages again after a while, and stop treating a site that is down as "no careers page". Builds on spec 15, and on spec 16 for URL handling.

## Problem

- The careers check runs once per row and never again, but jobs and visa wording change.
- Any fetch error counts as "no page", so a site that is down during the check is marked checked with nothing found, for good.

## Decisions For Approval

1. A new config value, `careers_recheck_days` (`SPONSOR_CAREERS_RECHECK_DAYS`, default 30). Rows checked longer ago than that are queued again.
2. Rows never checked go first, then the oldest checks, each by priority.
3. A new column, `careers_attempts` (unsigned tiny int, default 0). A fetch error makes the job throw, so it retries with backoff. After the job's final failure, `careers_attempts` goes up by one and `careers_checked_at` is left as it was, so a later batch tries again. After 3 failed checks in a row the row is left alone until `sponsors:retry` resets it (decision 5).
4. On a re-check that fails, the old careers fields are kept. On a re-check that succeeds, they are replaced, including clearing them when the page is gone.
5. `sponsors:retry` (spec 18) gains `--careers`, which sets `careers_attempts` to 0. It can be used with or without `--failed`.

## What Counts As A Fetch Error

`PageFetcher` reports errors separately from answers. It gains a result that says which one happened:

- **Error**: connection failure, timeout, download over the cap, too many redirects, or a 5xx or 429 answer.
- **Answer**: any other status (200, 404, 410, and so on).
- **Not allowed**: not http or https, or a private address. Treated as "no page", never retried.

In the careers finder:

- The homepage gives an error: the job throws, to retry later.
- Every candidate gives an error, and none answered 200: the job throws.
- Otherwise, as today: the first 200 wins, and no 200 means no page.

The verifier keeps its current behaviour (any error means `None`), because its result feeds the confidence score and a failed enrich is already retried.

## Migration

`add_careers_attempts_to_sponsors_table`: `careers_attempts` (unsigned tiny int, default 0).

## Selection

`readyForCareersCheck()` becomes: eligible (as in spec 15), `careers_attempts` under 3, and `careers_checked_at` null or older than `careers_recheck_days`. Ordered by `careers_checked_at` nulls first, then oldest, then `priority` descending, then `id`.

## In The Job

- Success (page found or not): set the four fields, `careers_checked_at` now, `careers_attempts` 0.
- `failed()`: `careers_attempts` plus 1. Other fields unchanged.
- The "already checked" guard becomes "checked within `careers_recheck_days`", so the job stays safe to run twice.

## Check When Done

- A row checked 31 days ago is queued again, one checked 29 days ago is not, and never-checked rows come first.
- A homepage connection error or 503 makes the job throw. After the final failure, `careers_attempts` is 1 and the old careers fields are kept.
- A row with 3 failed checks is not queued. `sponsors:retry --careers` resets it.
- A successful re-check replaces the fields, and clears them when the page is gone.
- A 404 homepage with no working fallback path still counts as "no page" (not an error).
- `PageFetcher` reports errors, answers, and not allowed correctly; the verifier tests pass unchanged.
- Config default and env key tested and pinned in `phpunit.xml`.
- Spec 15, `architecture-context.md`, and `deployment-context.md` updated.
