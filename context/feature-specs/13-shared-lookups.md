# Shared Lookups Between Teams

Let teams reuse each other's finished lookups to save API calls. Opt-in per team. Builds on the enrich job (spec 08) and team settings.

## Rule

- Each team has a `share_lookups` setting, default off.
- Owners and admins can turn it on or off (existing `TeamPermission::UpdateTeam`, via `TeamPolicy::update`).
- Two-way opt-in: a team only reuses results when its own setting is on, and only from teams whose setting is also on. A team with sharing off never gives or receives results.

## Migrations

- `add_share_lookups_to_teams_table`: `share_lookups` (boolean, default false). Add the cast and fillable entry on `Team`.
- `add_needs_second_check_to_sponsors_table`: `needs_second_check` (boolean, default false, indexed). Add the cast and fillable entry on `Sponsor`.

## Toggle

- Route: `PATCH settings/teams/{team}/lookup-sharing`, inside the existing `EnsureTeamMembership` group in `routes/settings.php`.
- Form Request authorises with `TeamPolicy::update` and validates `share_lookups` as boolean.
- UI: a "Share lookups with other teams" switch on `resources/js/pages/teams/edit.tsx`, with one line explaining it. Editable by owners and admins, read-only for members.

## Reuse In The Enrich Job

`App\Services\SharedLookup::find(Sponsor $sponsor): ?Sponsor`

- Returns null when the sponsor's team has sharing off.
- Otherwise finds a sponsor in another team with sharing on, the same normalised name (`CompanyName::normalise`) and the same town (case-insensitive), and status `done` or `skipped`. Prefer `confirmed`, then the highest confidence, then the most recently updated.

In `EnrichSponsor`, before any API call:

- Match found: copy `company_number`, `company_status`, `sic_codes`, `is_tech`, `website`, `confidence`, and `status`. Do not copy `confirmed` (each team confirms for itself). No API calls are made.
- If the source row is `confirmed` (a hand-checked website): cap the copied confidence at 74 and set `needs_second_check` to true. 74 is just under the 75 "trusted" threshold, so the row shows amber and lands in review.
- Copies from unconfirmed source rows keep their confidence and do not set the flag (the score was calculated the same way the team's own pipeline would).
- No match: run the normal pipeline.

## Second Check

- Confirming the row, or saving a website by hand, in the dashboard clears `needs_second_check` (and sets confidence to 100, as in spec 12).
- Dashboard: show a "Needs second check" badge on flagged rows, and add a "Needs second check" filter.
- Export: add a "Needs second check" column (Yes / No).
- `sponsors:status` shows a count of flagged rows.

## Rules

- The lookup is a plain database query. No HTTP. Keep it out of commands (invariant 1).
- Copying keeps the job safe to run twice (invariant 2).
- Never copy from a `pending`, `ch_done`, or `failed` row.

## Check When Done

- Owner and admin can toggle the setting. A member gets 403. A user from another team gets 403.
- Both teams on: the job copies the result and `Http::fake()` records no requests.
- Either team off: the job runs the normal pipeline.
- `confirmed` is never copied.
- Copy from a confirmed source: confidence is 74 and `needs_second_check` is true.
- Copy from an unconfirmed source: confidence unchanged and `needs_second_check` is false.
- Confirming or editing the website in the dashboard clears the flag.
- Badge, filter, export column, and status count work.
- Rows that are `failed` or not yet finished are never used as a source.
