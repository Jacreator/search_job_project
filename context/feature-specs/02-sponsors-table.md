# Sponsors Table and Model

The project is set up. Add the data model only.

## Migration

`sponsors` table:

- `id`
- `team_id` (foreign key to `teams`, cascade on delete)
- `name`, `town` (nullable), `county` (nullable), `route`, `rating`
- `company_number` (nullable), `company_status` (nullable)
- `sic_codes` (json, nullable), `is_tech` (boolean, nullable)
- `website` (nullable), `confidence` (unsigned tiny int, nullable)
- `confirmed` (boolean, default false) for manual review
- `status` (string, default `pending`, indexed)
- `attempts` (unsigned tiny int, default 0), `error` (text, nullable)
- timestamps
- unique on `team_id` + `name` + `town`

## Enum

`App\Enums\SponsorStatus`: `Pending`, `ChDone`, `Done`, `Skipped`, `Failed`, backed by the strings in `architecture-context.md`.

## Model

`App\Models\Sponsor` with casts for `sic_codes` (array), `is_tech` (bool), `confirmed` (bool), `status` (enum).

Relations:

- `Sponsor::team()` belongs to `Team`.
- `Team::sponsors()` has many `Sponsor` (small addition to the starter kit `Team` model).

Add query scopes: `pending()`, `readyForLookup()` (pending or ch_done, attempts under 3), `tech()`.

Add a factory (with a team from `Team::factory()`).

## Check When Done

- Migration runs and rolls back.
- Factory creates a valid sponsor.
- Scope tests pass.
- The same name + town can exist in two different teams, but not twice in one team.
