# Sponsors Table and Model

The project is set up. Add the data model only.

## Migration

`sponsors` table:

- `id`
- `team_id` (foreign key to `teams`, cascade on delete)
- `name`, `town` (nullable), `county` (nullable), `route`, `rating`
- `rating_grade` (string, 1 char, nullable: `A` or `B`)
- `rating_grade_manual` (boolean, default false): true when an owner or admin set the grade by hand (spec 12)
- `region` (string, nullable), `priority` (unsigned small int, default 0, indexed)
- `company_number` (nullable), `company_status` (nullable)
- `sic_codes` (json, nullable), `is_tech` (boolean, nullable), `tech_reason` (string, nullable)
- `website` (nullable), `confidence` (unsigned tiny int, nullable)
- `confirmed` (boolean, default false) for manual review
- `status` (string, default `pending`, indexed)
- `skip_reason` (string, nullable)
- `attempts` (unsigned tiny int, default 0), `error` (text, nullable)
- timestamps
- unique on `team_id` + `name` + `town`

## Enums

- `App\Enums\SponsorStatus`: `Pending`, `ChDone`, `Done`, `Skipped`, `Failed`, backed by the strings in `architecture-context.md`.
- `App\Enums\TechReason`: `Sic` (`sic`), `Keyword` (`keyword`), `KnownEmployer` (`known_employer`).
- `App\Enums\SkipReason`: `NoMatch` (`no_match`), `NotTech` (`not_tech`), `Inactive` (`inactive`), `BRating` (`b_rating`).

## Model

`App\Models\Sponsor` with casts for `sic_codes` (array), `is_tech` (bool), `rating_grade_manual` (bool), `tech_reason` (`TechReason`), `confirmed` (bool), `status` (`SponsorStatus`), `skip_reason` (`SkipReason`), `priority` (int).

`is_tech` is true when `tech_reason` is not null, and false when the sponsor was classified with no reason. It stays null until classification runs.

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
- `tech_reason` and `skip_reason` cast to their enums.
