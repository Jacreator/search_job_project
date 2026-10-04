# Export Command

## Command

`php artisan sponsors:export {--team=} {--min-confidence=50} {--town=} {--region=} {--confirmed}`

- `--team` is the team slug and is required.

## Rules

- Only the team's `done` rows with a website.
- Write to `storage/app/exports/{team-slug}_tech_sponsors_{date}.csv`.
- Columns: Name, Town, Region, Company number, SIC codes, Website, Confidence, Confirmed.
- Order by confidence, highest first.
- Use `lazy()` to stream rows.
- Add a UTF-8 BOM so Excel opens it cleanly.
- Escape cells starting with `=`, `+`, `-`, or `@` (see `security-context.md`).

## Added By Later Specs

These are not part of this spec. Each later spec extends the export when it is built:

- Spec 13: "Needs second check" column.
- Spec 14: "AI" column (Yes / No).
- Spec 15: "Careers URL", "Developer roles", and "Visa sponsorship" columns, and `--developer-roles` and `--visa-sponsorship` filters.

## Check When Done

- Feature test checks headers, filters (including region), and order.
- Rows from other teams never appear in the export.
