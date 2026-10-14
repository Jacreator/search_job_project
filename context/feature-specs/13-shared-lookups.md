# Shared Lookups Between Teams

Let teams reuse each other's finished lookups to save API calls. Opt-in per team. Builds on the enrich job (spec 08) and team settings.

## Rule

- Each team has a `share_lookups` setting, default off.
- Owners and admins can turn it on or off (existing `TeamPermission::UpdateTeam`, via `TeamPolicy::update`).
- Two-way opt-in: a team only reuses results when its own setting is on, and only from teams whose setting is also on. A team with sharing off never gives or receives results.

## Migrations

- `add_share_lookups_to_teams_table`: `share_lookups` (boolean, default false). Add the cast and fillable entry on `Team`.
- `add_needs_second_check_to_sponsors_table`: `needs_second_check` (boolean, default false, indexed). Add the cast and fillable entry on `Sponsor`.
- `add_normalised_name_to_sponsors_table`: `normalised_name` (string, default `''`, indexed), filled for existing rows by the migration. The `Sponsor` model sets it on every save and import sets it on insert. Added after the first build so the lookup uses an index instead of reading every row in the town (approved by James, 2026-10-14).

## Toggle

- Route: `PATCH settings/teams/{team}/lookup-sharing`, inside the existing `EnsureTeamMembership` group in `routes/settings.php`.
- Form Request authorises with `TeamPolicy::update` and validates `share_lookups` as boolean.
- UI: a "Share lookups with other teams" checkbox on `resources/js/pages/teams/edit.tsx`, with one line explaining it. Editable by owners and admins, read-only text for members. A checkbox, not a switch, because the starter kit has no switch component and adding one needs a new npm dependency (same control as the spec 11 flag).

## Reuse In The Enrich Job

`App\Services\SharedLookup::find(Sponsor $sponsor): ?Sponsor`

- Returns null when the sponsor's team has sharing off.
- Otherwise finds a sponsor in another team with sharing on, the same normalised name (`CompanyName::normalise`) and the same town (case-insensitive), and status `done` or `skipped`. Never use a source row whose `skip_reason` is `BRating`. Prefer `confirmed`, then the highest confidence, then the most recently updated.

In `EnrichSponsor`, before any API call:

- Match found: copy `company_number`, `company_status`, `sic_codes`, `is_tech`, `tech_reason`, `skip_reason`, `website`, `confidence`, and `status`. Do not copy `confirmed` (each team confirms for itself). Do not copy `region`, `priority`, `rating_grade`, or `rating_grade_manual`: each team gets these from its own import. No API calls are made.
- Also copy `is_ai` and `ai_source` (added by spec 14).
- Once spec 15 is built, also copy `careers_url`, `mentions_developer_roles`, `visa_sponsorship`, and `careers_checked_at`.
- If the source row is `confirmed` (a hand-checked website): cap the copied confidence at 74 and set `needs_second_check` to true. 74 is just under the 75 "trusted" threshold, so the row shows amber and lands in review.
- Copies from unconfirmed source rows keep their confidence and do not set the flag (the score was calculated the same way the team's own pipeline would).
- No match: run the normal pipeline.
- The lookup runs for `pending` and `ch_done` rows, after `attempts` is incremented, so a copy counts as one attempt.
- Matching: on the stored, indexed `sponsors.normalised_name` (`CompanyName::normalise(name)`), then the town (`lower(town)`), other sharing teams, and the statuses allowed. The exact name is checked again in PHP, because the MySQL collation also ignores accents. Rows from soft-deleted teams are never used.

## Second Check

- Confirming the row, or saving a website by hand, in the dashboard clears `needs_second_check` (and sets confidence to 100, as in spec 12).
- Dashboard: show a "Needs second check" badge on flagged rows, and add a "Needs second check" filter.
- Export: add a "Needs second check" column (Yes / No).
- `sponsors:status` shows a count of flagged rows.

## Rules

- The lookup is a plain database query. No HTTP. Keep it out of commands (invariant 1).
- Copying keeps the job safe to run twice (invariant 2).
- Never copy from a `pending`, `ch_done`, or `failed` row.
- Never copy from a row skipped with `BRating`. Rating is handled by each team's own import.

## Check When Done

- Owner and admin can toggle the setting. A member gets 403. A user from another team gets 403.
- Both teams on: the job copies the result and `Http::fake()` records no requests.
- Either team off: the job runs the normal pipeline.
- `confirmed` is never copied.
- `tech_reason` and `skip_reason` are copied.
- `region`, `priority`, `rating_grade`, and `rating_grade_manual` are never copied. The target keeps its own values.
- A source row skipped with `BRating` is never used.
- `is_ai` and `ai_source` are copied (tested in `AiTaggingTest`).
- After spec 15: `careers_url`, `mentions_developer_roles`, `visa_sponsorship`, and `careers_checked_at` are copied (spec 15 adds this test).
- Copy from a confirmed source: confidence is 74 and `needs_second_check` is true.
- Copy from an unconfirmed source: confidence unchanged and `needs_second_check` is false.
- Confirming or editing the website in the dashboard clears the flag.
- Badge, filter, export column, and status count work.
- Rows that are `failed` or not yet finished are never used as a source.
