# Sector Labels

**Status: draft, awaiting James's approval.**

Give every sponsor with SIC codes a sector, taken from the SIC 2007 section (A to U), so users see "Construction" or "Human health and social work activities" instead of "Not tech". Builds on specs 04, 05, 08, 12, 13, and 10. Tech detection (`is_tech`, `tech_reason`) does not change.

## Migration

`add_sector_to_sponsors_table`:

- `sector` (string(1), nullable, indexed): the SIC 2007 section letter, `A` to `U`.

Add the fillable entry and docblock on `Sponsor`. No cast: it is a plain letter.

### Why A Letter In A String Column, Not An Enum

- The section letter is the official, stable code. The names are only labels.
- The section names live in config (below), as asked. An enum would be a second copy of the same 21 sections that could drift from config.
- Validation and labels both read the config list, so there is one source of truth. Larastan still sees a `string|null`.

## Config

Add to `config/sponsor-finder.php`, in a new "Sectors" section:

- `sic_divisions`: two-digit SIC division to section letter, one entry per division in SIC 2007:

  | Divisions | Section |
  | --------- | ------- |
  | 01 to 03  | A       |
  | 05 to 09  | B       |
  | 10 to 33  | C       |
  | 35        | D       |
  | 36 to 39  | E       |
  | 41 to 43  | F       |
  | 45 to 47  | G       |
  | 49 to 53  | H       |
  | 55 to 56  | I       |
  | 58 to 63  | J       |
  | 64 to 66  | K       |
  | 68        | L       |
  | 69 to 75  | M       |
  | 77 to 82  | N       |
  | 84        | O       |
  | 85        | P       |
  | 86 to 88  | Q       |
  | 90 to 93  | R       |
  | 94 to 96  | S       |
  | 97 to 98  | T       |
  | 99        | U       |

  Written out as explicit keys (`'01' => 'A'`, `'02' => 'A'`, ...), not ranges, so a lookup is one array read. Division numbers SIC 2007 does not use (04, 34, 40, 44, 48, 54, 57, 67, 76, 83, 89) are left out, so a code starting with one of them has no section.

- `sector_names`: section letter to name, the official SIC 2007 section titles:

  | Letter | Name                                                                                                                       |
  | ------ | -------------------------------------------------------------------------------------------------------------------------- |
  | A      | Agriculture, forestry and fishing                                                                                          |
  | B      | Mining and quarrying                                                                                                       |
  | C      | Manufacturing                                                                                                              |
  | D      | Electricity, gas, steam and air conditioning supply                                                                        |
  | E      | Water supply; sewerage, waste management and remediation activities                                                        |
  | F      | Construction                                                                                                               |
  | G      | Wholesale and retail trade; repair of motor vehicles and motorcycles                                                       |
  | H      | Transportation and storage                                                                                                 |
  | I      | Accommodation and food service activities                                                                                  |
  | J      | Information and communication                                                                                              |
  | K      | Financial and insurance activities                                                                                         |
  | L      | Real estate activities                                                                                                     |
  | M      | Professional, scientific and technical activities                                                                          |
  | N      | Administrative and support service activities                                                                              |
  | O      | Public administration and defence; compulsory social security                                                              |
  | P      | Education                                                                                                                  |
  | Q      | Human health and social work activities                                                                                    |
  | R      | Arts, entertainment and recreation                                                                                         |
  | S      | Other service activities                                                                                                   |
  | T      | Activities of households as employers; undifferentiated goods- and services-producing activities of households for own use |
  | U      | Activities of extraterritorial organisations and bodies                                                                    |

- `sector_ignored_sic_codes`: `99999` (Dormant Company) and `74990` (Non-trading company). Companies House uses these as placeholders. They say nothing about the business, and `99999` would otherwise land in section U.

## Class

`App\Support\SectorClassifier::sectorFor(array $sicCodes): ?string`, pure, with the three config lists injected (`#[Config(...)]`, like `EmployerClassifier`). No database or HTTP.

For each code, in the order Companies House gave them:

1. Trim it. Skip it unless it is 4 or 5 digits.
2. Skip it if it is in `sector_ignored_sic_codes`.
3. Take the first two digits and look up the section in `sic_divisions`. Skip it if the division is not there.

Then:

- No section left: null.
- Otherwise, the section that appears most often wins.
- A tie goes to the section of the earliest code in the list (Companies House usually lists the main activity first).

Example: `['62012', '62020', '70229']` gives J (two J, one M). `['70229', '62012']` gives M (a tie, M comes first). `['99999']` gives null.

## In The Enrich Job

- Right after the Companies House profile is fetched (spec 08, step 1), set `sector` from `SectorClassifier` in the same update as `sic_codes`.
- Shared lookups (spec 13) copy `sector`.
- Website search stays tech-only. No new API calls for any row.

## Command

`php artisan sponsors:fill-sectors {--team=}`

- For every row with `sic_codes` not null, set `sector` from the stored codes. Rows where the result does not change are not written.
- `chunkById` over the rows, writes in a transaction per chunk.
- Rows without SIC codes (pending, no match) are left as they are.
- `--team=` limits it to one team (slug). Unknown team: fail.
- Prints how many rows were set, changed, and left without a sector.
- Database only. No HTTP (invariant 1). Safe to run again, for example after a config change.

## Dashboard

- Each row sends `sector` (the letter). The page gets a `sectors` prop: letter to name, from config, so labels come from one place.
- A "Sector" column after "SIC codes", showing the sector name, or "-" when there is none.
- Rows skipped as not tech show their sector name in the status cell where "Not tech" is shown today. With no sector, they show "Sector unknown".
- The skip reason summary shows `not_tech` as "Other sectors" instead of "Not tech".
- A "Sector" filter (`Select`, query key `sector`): any, or one of the sectors the team has rows in, ordered by name. Validated against the `sector_names` keys.

## Export

- A "Sector" column (the sector name, blank when none), after "SIC codes".

## Rules

- Tech detection is unchanged. A tech row can have any sector, for example J, or M for a consultancy with a tech SIC code.
- The search, verify, and careers steps still only run for tech rows. Sectors never cause an API call.
- The `NotTech` skip reason stays in the database and in `sponsors:status`. Only the dashboard wording changes.

## Check When Done

- Migration runs and rolls back.
- `SectorClassifier` unit tests: one code per section (A to U), a 4-digit code, a code with spaces, a non-numeric code, an unknown division, `99999` and `74990` ignored, only ignored codes giving null, an empty list, most common wins, a tie going to the earliest code, and config values read.
- Config tests: every division maps to a letter that has a name, and every letter has at least one division.
- Enrich job: `sector` set after the profile for tech and non-tech rows, null for no match, no extra HTTP request (`Http::fake()` counts unchanged).
- Shared lookups copy `sector`.
- Command: fills rows from stored codes, leaves rows without codes, does not rewrite unchanged rows, respects `--team`, fails on an unknown team, makes no HTTP request.
- Dashboard: sector in row props and the `sectors` prop, the sector filter, an invalid sector rejected, and a not tech row showing its sector name.
- Export: sector column, and blank when none.
- Live count (no API calls): run the command on the local rows and report the counts per sector.
- `data-model-context.md`, `architecture-context.md`, `ui-context.md`, and specs 08, 10, 12, and 13 updated.
