# AI Name Rule

**Status: draft, awaiting James's approval.**

Stop false AI tags from names, and re-tag rows already looked up. Builds on spec 14.

## Problem

`AiDetector::fromName` tags any name with an AI term. "Ai" is also a person's name or initials, so "Ai Beauty LTD" and "AI & CO LTD TA Flying Horse Restaurant" get tagged. 299 of the 122,938 register names match the AI terms, and some are clearly not AI companies.

Rows looked up before spec 14 have no AI tag at all.

## Decisions For Approval

1. A name match only tags the row when the row is also tech (`is_tech` true, after classification). The homepage check is unchanged: it only runs on active tech rows anyway.
2. A new command, `php artisan sponsors:retag-ai {--team=}`, applies the name rule again to rows already past the Companies House step. Database only, no HTTP. Homepage tags are left as they are, because re-checking them would need every homepage fetched again.

## In The Enrich Job

- After classification: if `is_tech` is true and `AiDetector::fromName` is true, set `is_ai` true and `ai_source` `name`. Otherwise leave both unchanged.

## Command

For rows with `is_tech` not null (classification has run), in chunks:

- Tech and the name matches, and not tagged yet: set `is_ai` true, `ai_source` `name`.
- Tagged from the name (`ai_source` `name`), but not tech or the name no longer matches: set `is_ai` false, `ai_source` null.
- Tagged from the homepage: unchanged.
- Prints how many rows were tagged and how many were untagged.
- `--team=` limits it to one team. Unknown team: fail.

The rule itself lives in `AiDetector` (or a small `AiNameTag` helper), so the job and the command use the same code.

## Check When Done

- Job: a tech row with an AI name is tagged `name`. A non-tech row with an AI name (for example "Ai Beauty Ltd" with a beauty SIC code) is not tagged. The homepage case still works.
- Command: tags a tech row with an AI name, untags a non-tech row tagged from the name, leaves homepage tags alone, skips rows not yet classified, respects `--team`, fails on an unknown team.
- Live count (no API calls): how many local rows the command tags and untags.
- Spec 14 and `architecture-context.md` updated to the new rule.
