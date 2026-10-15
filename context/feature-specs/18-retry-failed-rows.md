# Retry Failed Rows

**Status: draft, awaiting James's approval.**

Let failed rows go back into the lookup queue. Builds on specs 08 and 09.

## Problem

A row becomes `failed` after three real errors, for example a missing or wrong API key, or an API outage. `readyForLookup()` only picks `pending` and `ch_done` rows, so nothing ever picks a failed row up again, even after the cause is fixed.

## Decisions For Approval

1. A new command, `php artisan sponsors:retry --failed {--team=} {--dry-run}`. It resets the rows and does not queue jobs; the next hourly `sponsors:enrich` (or a manual run) picks them up.
2. A retried row goes back to `ch_done` when it already has its Companies House result (`company_number` and `company_status` set), so the paid search is the only call made again. Otherwise it goes back to `pending`.
3. This is a second, deliberate exception to invariant 3 ("a row never skips a pipeline state"), next to rating changes. `architecture-context.md` is updated to say so.

## Command

- `--failed` is required for now, so the command never resets anything by accident. Without it the command fails with a clear message.
- `--team=` limits it to one team (slug). Unknown team: fail.
- `--dry-run`: change nothing, print how many rows would be reset, grouped by error message (the first 80 characters, most common first), so the cause is easy to see.
- Otherwise, for each failed row: status `ch_done` or `pending` (decision 2), `attempts` 0, `error` null, `skip_reason` null. Done in chunks (`chunkById`), in a transaction per chunk.
- Prints how many rows went back to `pending` and how many to `ch_done`.
- Database only. No HTTP (invariant 1).

## Check When Done

- A failed row with no Companies House result goes back to `pending`, and one with it to `ch_done`. Attempts and error are cleared.
- Rows that are not failed are never changed.
- `--team` limits the reset, and an unknown team fails.
- No `--failed`: fails and changes nothing.
- `--dry-run` changes nothing and prints the error groups.
- After a reset, `sponsors:enrich` queues the rows again (`Queue::fake()`).
- `architecture-context.md` (pipeline states, invariant 3) and spec 09 updated.
