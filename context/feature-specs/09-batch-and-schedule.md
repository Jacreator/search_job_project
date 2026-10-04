# Batch Command and Schedule

Process the register over time, Sheffield and Yorkshire first.

## Command

`php artisan sponsors:enrich {--limit=} {--town=} {--region=} {--team=}`

- Default limit from config. The limit is per run, across all teams.
- Select `readyForLookup()` rows, ordered by `priority` descending, then `id`.
- Optional town, region, and team (slug) filters.
- Dispatch one `EnrichSponsor` job per row.
- Print how many were queued.

## Status Command

`php artisan sponsors:status {--team=}` prints, for one team or all teams:

- counts per status
- counts per `is_tech`
- skip counts per `skip_reason`

## Schedule

In `routes/console.php`:

- `sponsors:enrich` hourly, `withoutOverlapping()`, across all teams.

## Check When Done

- Feature test: command queues the right rows (use `Queue::fake()`), including the team and region filters.
- Higher priority rows are queued before lower ones, and ties go by id.
- Status command shows skip counts by reason.
- `php artisan schedule:list` shows the hourly task.
- Manual test: 50 Sheffield rows processed end to end with real keys.
