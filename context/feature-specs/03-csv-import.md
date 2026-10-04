# CSV Import Command

The model exists. Add the import command only.

## Command

`php artisan sponsors:import {file} {--team=}`

- `--team` is the team slug and is required.

## Rules

- Fail with a clear message if the team does not exist.
- Read the file row by row with `SplFileObject` or `fgetcsv`. Never load it all.
- Map columns by header name, not position.
- Keep only rows whose Route is in `config('sponsor-finder.routes')`.
- Trim names and towns.
- Upsert on team + name + town. Existing rows keep their lookup results.
- Insert in chunks of 500 for speed.
- Show a progress bar and a final count of new and existing rows.
- Fail with a clear message if the file or a required header is missing.

## Rating

- Parse `rating_grade` from the "Type & Rating" column, for example "Worker (A rating)" gives `A`. Anything that does not match gives null.
- Parsing lives in a small pure helper, `App\Support\SponsorRating::grade(string): ?string`.
- Add `skip_b_rated` to `config/sponsor-finder.php` (`SPONSOR_SKIP_B_RATED`, default true).
- When `skip_b_rated` is true, new B-rated rows are imported with status `skipped` and skip reason `BRating`. They never reach the enrich job.

## Location Priority

- Set `region` and `priority` from `config('sponsor-finder.priority_locations')`, matching the town case-insensitively.
- Lookup lives in a small pure helper, `App\Support\PriorityLocations::for(?string $town)`, returning region and priority.
- Unlisted or empty towns get region null and priority 0.

## Check When Done

- Feature test imports a small fixture CSV into a team.
- Re-importing the same file creates no duplicates and keeps existing websites.
- Importing the same file into a second team creates separate rows.
- Rows with other routes are ignored.
- `rating_grade` is parsed for A and B rows, and null for an unexpected value.
- B-rated rows are skipped with reason `BRating` when `skip_b_rated` is true, and imported as `pending` when it is false.
- "sheffield", "SHEFFIELD" and "Sheffield" all get region Yorkshire and priority 100. An unlisted town gets null and 0.
