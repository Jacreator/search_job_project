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
- Show a progress bar and a final count of new rows, existing rows, rows moved to skipped (B rating), and rows moved back to pending (A rating).
- Fail with a clear message if the file or a required header is missing.

## Rating

- Parse `rating_grade` from the "Type & Rating" column, for example "Worker (A rating)" gives `A`. Anything that does not match gives null.
- Parsing lives in a small pure helper, `App\Support\SponsorRating::grade(string): ?string`.
- The status rules for a grade change live in one place, `App\Services\RatingChange::apply(Sponsor $sponsor, string $grade)`, used by both import and the dashboard (spec 12).
- Add `skip_b_rated` to `config/sponsor-finder.php` (`SPONSOR_SKIP_B_RATED`, default true).
- When `skip_b_rated` is true, new B-rated rows are imported with status `skipped` and skip reason `BRating`. They never reach the enrich job.

## Rating Changes On Re-import

Re-import updates `rating_grade` on existing rows when the register gives a grade. Compare the stored grade with the new one. When `skip_b_rated` is true:

- A becomes B: set status `skipped` and skip reason `BRating`, whatever the current status. Keep all lookup results, the website, and `confirmed`.
- B becomes A, and the row is `skipped` with skip reason `BRating`: set status `pending` and clear `skip_reason`. Keep any existing lookup results. The enrich job is safe to run again.
- Rows skipped for any other reason are not changed by a rating change.
- No rating change: nothing changes.

When `skip_b_rated` is false, only `rating_grade` is updated.

## Missing Grades

- If the register value cannot be parsed, the stored grade is never overwritten with null. A grade set by hand is kept.
- A missing grade becoming B on re-import is treated like A to B. A missing grade becoming A changes only the grade.
- A grade from the register replaces a hand-set grade and sets `rating_grade_manual` to false. The register is the source of truth.
- Rows still missing a grade are fixed by an owner or admin in the dashboard (spec 12).

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
- Re-import A to B: a `done` row becomes `skipped` with `BRating`, and keeps its website, lookup results, and `confirmed`.
- Re-import B to A: a row skipped with `BRating` becomes `pending` with no skip reason, and keeps its lookup results.
- Re-import B to A on a row skipped for another reason (for example `NotTech`): status and skip reason are unchanged.
- Re-import with no rating change: the row is unchanged.
- The summary reports the moved to skipped and back to pending counts.
- An unparseable rating on re-import keeps the stored grade.
- Missing to B on re-import skips the row with `BRating`. Missing to A changes only the grade.
- A register grade replaces a hand-set grade and clears `rating_grade_manual`.
- "sheffield", "SHEFFIELD" and "Sheffield" all get region Yorkshire and priority 100. An unlisted town gets null and 0.
