# Data Model Context

## Conventions

- Migration files are named for the change they make (`create_x_table`, `add_y_to_x_table`), Laravel's default convention.
- New models declare their fillable columns explicitly (the starter kit uses the `#[Fillable([...])]` attribute; follow that). Never `$guarded = []`.
- Typed columns use casts (`casts()` method), including enums and JSON arrays.
- Every new model gets a factory.
- Declare relationships on both sides only where both sides are used.
- Do not store raw API responses. Only the fields the app uses.

## Starter Kit Tables (existing, change only through a spec)

| Table              | Purpose                                      |
| ------------------ | -------------------------------------------- |
| `users`            | Accounts, two-factor columns, `current_team_id` |
| `teams`            | Teams (`slug` used in URLs, soft deletes, + `share_lookups`, spec 13) |
| `team_members`     | User membership and role per team (+ `can_edit_sponsors`, spec 11) |
| `team_invitations` | Pending invites                              |
| `passkeys`         | WebAuthn passkeys                            |
| `password_reset_tokens`, `sessions` | Auth support               |
| `cache`, `cache_locks` | Database cache                           |
| `jobs`, `job_batches`, `failed_jobs` | Database queue             |

## Sponsor Finder Tables

Defined in `feature-specs/02-sponsors-table.md`. Summary:

| Table      | Purpose                                                        |
| ---------- | -------------------------------------------------------------- |
| `sponsors` | Register rows, Companies House results, website, pipeline state, owned by a team |

Key rules:

- `team_id` is a foreign key to `teams`, cascade on delete.
- Unique on `team_id` + `name` + `town`.
- `status` is backed by `App\Enums\SponsorStatus` and indexed.
- `needs_second_check` (spec 13) marks a lookup copied from another team's hand-confirmed row. Cleared when this team confirms it.
- `Team` has a `sponsors()` has-many relation. `Sponsor` has a `team()` belongs-to relation.

When a later spec adds a column (for example `careers_url` in spec 14), add it with a new `add_..._to_sponsors_table` migration and update this file.
