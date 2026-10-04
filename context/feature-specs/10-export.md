# Export Command

## Command

`php artisan sponsors:export {--team=} {--min-confidence=50} {--town=} {--confirmed}`

- `--team` is the team slug and is required.

## Rules

- Only the team's `done` rows with a website.
- Write to `storage/app/exports/{team-slug}_tech_sponsors_{date}.csv`.
- Columns: Name, Town, Company number, SIC codes, Website, Confidence, Confirmed.
- Order by confidence, highest first.
- Use `lazy()` to stream rows.
- Add a UTF-8 BOM so Excel opens it cleanly.
- Escape cells starting with `=`, `+`, `-`, or `@` (see `security-context.md`).

## Check When Done

- Feature test checks headers, filters, and order.
- Rows from other teams never appear in the export.
