# Export Command

## Command

`php artisan sponsors:export {--team=} {--min-confidence=50} {--town=} {--region=} {--confirmed} {--scale-up}`

- `--team` is the team slug and is required.
- `--scale-up` keeps only Scale-up sponsors (`Sponsor::scaleUp()`).

## Rules

- Only the team's `done` rows with a website.
- Write to `storage/app/exports/{team-slug}_tech_sponsors_{date}.csv`.
- Columns: Name, Town, Region, Company number, SIC codes, Website, Confidence, Confirmed, Scale-up (Yes / No, from `route`).
- Order by confidence, highest first.
- Use `lazy()` to stream rows.
- Add a UTF-8 BOM so Excel opens it cleanly.
- Escape cells starting with `=`, `+`, `-`, or `@` (see `security-context.md`).

## Choices Made When Built

- `{date}` is today's date as `YYYY-MM-DD`. A second export for the same team on the same day replaces the first. The file is written as `.part` and renamed, so a failed export leaves no partial file.
- `--min-confidence` must be a whole number from 0 to 100. It applies together with every other filter, including `--confirmed`.
- `--town` and `--region` match ignoring case, like `sponsors:enrich`.
- Rows with the same confidence are ordered by name, then id.
- SIC codes are joined with a comma and a space in one cell. Confirmed is Yes / No. Missing values are empty cells.
- When nothing matches, the file is still written with the headers only, and the command reports 0 sponsors.
- The command fails on a missing `--team`, an unknown team, or a bad `--min-confidence`.
- `App\Services\SponsorExport` writes the file, `App\Services\ExportFilters` carries the options, and `App\Support\CsvCell::safe()` does the formula escaping.

## Added By Later Specs

These are not part of this spec. Each later spec extends the export when it is built:

- Spec 13: "Needs second check" column.
- Spec 14: "AI" column (Yes / No).
- Spec 15: "Careers URL", "Developer roles", and "Visa sponsorship" (Offered / Not offered / blank) columns, and `--developer-roles` and `--visa-sponsorship=offered|not_offered` filters.

## Check When Done

- Feature test checks headers, filters (including region and `--scale-up`), and order.
- The Scale-up column is Yes for a sponsor whose `route` includes Scale-up, alone or with other routes.
- Rows from other teams never appear in the export.
