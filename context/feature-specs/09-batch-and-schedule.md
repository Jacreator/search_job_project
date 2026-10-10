# Batch Command and Schedule

Process the register over time, Sheffield and Yorkshire first.

## Command

`php artisan sponsors:enrich {--limit=} {--town=} {--region=} {--team=}`

- Default limit from config. The limit is per run, across all teams.
- Select `readyForLookup()` rows, ordered by `priority` descending, then `id`.
- Optional town, region, and team (slug) filters. Town and region match ignoring case. An unknown team fails.
- `--limit` must be a whole number above 0, otherwise the command fails and queues nothing.
- Dispatch one `EnrichSponsor` job per row.
- Print how many were queued. `EnrichSponsor` is unique per sponsor (spec 08), so a row whose job is still queued or waiting on a retry is left out, and the command prints how many were left out.

## Status Command

`php artisan sponsors:status {--team=}` prints, for one team or all teams:

- counts per status
- counts per `is_tech`
- skip counts per `skip_reason`

Every status and skip reason is listed, with 0 when there are none. Tech shows yes, no, and not checked (null). A skipped row with no skip reason is shown as "none" (it should never happen, see invariant 9). An unknown team fails.

## Schedule

In `routes/console.php`:

- `sponsors:enrich` hourly, `withoutOverlapping()`, across all teams.

## Check When Done

- Feature test: command queues the right rows (use `Queue::fake()`), including the team and region filters.
- Higher priority rows are queued before lower ones, and ties go by id.
- Status command shows skip counts by reason.
- `php artisan schedule:list` shows the hourly task.
- Manual test: 50 Sheffield rows processed end to end with real keys.
