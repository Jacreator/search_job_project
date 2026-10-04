# Batch Command and Schedule

Process the register over time.

## Command

`php artisan sponsors:enrich {--limit=} {--town=} {--team=}`

- Default limit from config. The limit is per run, across all teams.
- Select `readyForLookup()` rows, ordered by id, optional town and team (slug) filters.
- Dispatch one `EnrichSponsor` job per row.
- Print how many were queued.

## Status Command

`php artisan sponsors:status {--team=}` prints a table of counts per status and per `is_tech`, for one team or all teams.

## Schedule

In `routes/console.php`:

- `sponsors:enrich` hourly, `withoutOverlapping()`, across all teams.

## Check When Done

- Feature test: command queues the right rows (use `Queue::fake()`), including the team filter.
- `php artisan schedule:list` shows the hourly task.
- Manual test: 50 Sheffield rows processed end to end with real keys.
