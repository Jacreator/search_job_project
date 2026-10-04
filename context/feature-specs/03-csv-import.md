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

## Check When Done

- Feature test imports a small fixture CSV into a team.
- Re-importing the same file creates no duplicates and keeps existing websites.
- Importing the same file into a second team creates separate rows.
- Rows with other routes are ignored.
