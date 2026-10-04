# Data Model Context

## Conventions

- Migration files are named for the change they make (`create_x_table`, `add_y_to_x_table`), Laravel's default convention.
- New models declare their fillable columns explicitly (the starter kit uses the `#[Fillable([...])]` attribute; follow that). Never `$guarded = []`.
- Typed columns use casts (`casts()` method), including enums and JSON arrays.
- Every new model gets a factory.
- Declare relationships on both sides only where both sides are used.
- Do not store raw API responses or page HTML. Only the fields the app uses.

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

| Table      | Purpose                                                        |
| ---------- | -------------------------------------------------------------- |
| `sponsors` | Register rows, Companies House results, website, careers signals, pipeline state, owned by a team |

Key rules:

- `team_id` is a foreign key to `teams`, cascade on delete.
- Unique on `team_id` + `name` + `town`.
- `status` is backed by `App\Enums\SponsorStatus` and indexed.
- `Team` has a `sponsors()` has-many relation. `Sponsor` has a `team()` belongs-to relation.

## Sponsor Columns By Spec

| Column                      | Type                                   | Added by | Notes |
| --------------------------- | -------------------------------------- | -------- | ----- |
| `team_id`                   | foreign key                            | spec 02  | Owner team |
| `name`, `town`, `county`, `route`, `rating` | string (town, county nullable) | spec 02  | From the register |
| `rating_grade`              | string(1), nullable                    | spec 02  | `A` or `B`, parsed at import and updated on re-import (spec 03). Never shared |
| `rating_grade_manual`       | boolean, default false                 | spec 02  | Grade set by an owner or admin (spec 12). Cleared when the register gives a grade. Never shared |
| `region`                    | string, nullable                       | spec 02  | Set at import from `priority_locations` (spec 03). Never shared |
| `priority`                  | unsigned small int, default 0, indexed | spec 02  | Set at import (spec 03). Higher goes first. Never shared |
| `company_number`, `company_status` | string, nullable                | spec 02  | Companies House |
| `sic_codes`                 | json, nullable                         | spec 02  | Cast to array |
| `is_tech`                   | boolean, nullable                      | spec 02  | True when `tech_reason` is not null |
| `tech_reason`               | string, nullable                       | spec 02  | Cast to `App\Enums\TechReason` |
| `website`                   | string, nullable                       | spec 02  | Homepage |
| `confidence`                | unsigned tiny int, nullable            | spec 02  | 0 to 100 |
| `confirmed`                 | boolean, default false                 | spec 02  | Manual review. Never shared |
| `status`                    | string, default `pending`, indexed     | spec 02  | Cast to `App\Enums\SponsorStatus` |
| `skip_reason`               | string, nullable                       | spec 02  | Cast to `App\Enums\SkipReason`. Set on every skip. Cleared when a `BRating` row returns to `pending` on re-import |
| `attempts`, `error`         | tiny int default 0, text nullable      | spec 02  | Retry state |
| `needs_second_check`        | boolean, default false, indexed        | spec 13  | Copied from another team's confirmed row. Cleared when this team confirms it |
| `is_ai`                     | boolean, default false, indexed        | spec 14  | AI tag |
| `ai_source`                 | string, nullable                       | spec 14  | `name` or `homepage` |
| `careers_url`               | string, nullable                       | spec 15  | Careers page |
| `mentions_developer_roles`  | boolean, nullable                      | spec 15  | Null until checked, or when no page was found |
| `visa_sponsorship`          | string, nullable                       | spec 15  | Cast to `App\Enums\VisaSponsorship` (`offered`, `not_offered`). Null when unchecked, no page, or no visa wording |
| `careers_checked_at`        | timestamp, nullable                    | spec 15  | Set when the careers job finishes |

Spec 02 creates the table. Specs 13, 14, and 15 add columns with their own `add_..._to_sponsors_table` migrations. When a later spec adds a column, add it here too.

## Enums

| Enum                      | Cases                                            |
| ------------------------- | ------------------------------------------------ |
| `App\Enums\SponsorStatus` | `Pending`, `ChDone`, `Done`, `Skipped`, `Failed` |
| `App\Enums\TechReason`    | `Sic`, `Keyword`, `KnownEmployer`                |
| `App\Enums\SkipReason`    | `NoMatch`, `NotTech`, `Inactive`, `BRating`      |
| `App\Enums\VisaSponsorship` | `Offered`, `NotOffered` (spec 15)             |
