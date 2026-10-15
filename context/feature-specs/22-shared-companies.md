# Shared Companies Table

**Status: proposal only. Build this only if James decides the app will have other users or teams.**

Store each company once, shared by every team, with only team-specific fields kept per team. This would replace spec 13's copy-between-teams approach. A schema redesign, so it needs James's go-ahead (`ai-workflow-rules.md`).

## Problem

Each team imports its own copy of the register (about 123,000 rows) and runs its own lookups. Spec 13 lets sharing teams copy each other's results, but every team still stores everything. With a few teams that is fine. With many teams it means many copies of the same data and the same lookups.

## Decision For Approval

1. Go multi-user or not. If not, this spec is not built and spec 13 stays.

If yes, the decisions below need approval too:

2. A `companies` table holds everything about the company itself: register fields (name, town, county, routes, rating, rating grade), `normalised_name`, Companies House results, tech classification, website, confidence, AI tag, careers fields, lookup `status`, `skip_reason`, `attempts`, and `error`. Unique on `normalised_name` + town.
3. `sponsors` becomes the team's view of a company: `team_id`, `company_id`, and the team-only fields: `confirmed`, `region`, `priority`, `rating_grade_manual`, and a new `notes` text field. Unique on `team_id` + `company_id`.
4. Lookups run once per company, not per team. The enrich and careers jobs take a `Company`. Priority for the queue is the highest priority any team gives the company.
5. A hand-saved website is a team field (`website_override` on `sponsors`), so one team's edit does not change the shared result. The dashboard shows the override when there is one.
6. Spec 13 is removed: `share_lookups`, `needs_second_check`, and `SharedLookup` go, because every team sees the same lookup.
7. Import reads the register once into `companies`, then links each team to the companies on its routes.

## Migration Plan (Outline)

1. Create `companies`. Fill it from the existing `sponsors` rows, merging rows with the same `normalised_name` and town (the best lookup wins, by the spec 13 order: confirmed, confidence, most recent).
2. Add `company_id` and the team fields to `sponsors`, and link every row.
3. Move the code: jobs, commands, dashboard, and export read company fields through the relation.
4. Drop the moved columns from `sponsors`, and the spec 13 columns.
5. Each step runs and rolls back on its own, and is checked on the local 122,938 rows.

## Check When Done (Outline)

- The data migration keeps every team's view the same, checked by counts and spot checks before and after.
- One lookup per company, whichever teams have it.
- A team's confirmed flag, notes, and website override never show for another team.
- Every web query is still scoped to the current team (invariant 7).
- All existing feature tests rewritten for the new model, and `composer ci:check` passes.

This spec would be written out in full, in the same detail as specs 16 to 21, once decision 1 is yes.
