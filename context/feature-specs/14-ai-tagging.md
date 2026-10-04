# AI Tagging

Tag sponsors that look like AI companies, so AI Engineer roles are easy to find. Builds on the enrich job (spec 08) and the verifier (spec 07).

## Migration

`add_ai_fields_to_sponsors_table`:

- `is_ai` (boolean, default false, indexed)
- `ai_source` (string, nullable: `name` or `homepage`)

Add casts and fillable entries on `Sponsor`.

## Config

Add `ai_keywords` to `config/sponsor-finder.php`:

`ai, artificial intelligence, machine learning, ml, deep learning, llm, generative, computer vision, nlp, data science, robotics`

## Class

`App\Support\AiDetector` with two pure methods:

- `fromName(string $name): bool` - true when the normalised name contains an AI term.
- `fromText(string $text): bool` - true when the page text contains an AI term.

Rules:

- Case-insensitive, whole words or whole phrases only, so "ai" does not match "email" or "maintain", and "ml" does not match "html".
- No database or HTTP.

## In The Enrich Job

- After classification (spec 08 step 1): if `AiDetector::fromName` is true, set `is_ai` true and `ai_source` to `name`.
- After verification (spec 08 step 4): if `is_ai` is still false and `AiDetector::fromText` is true on the `VerifiedPage` text (spec 07), set `is_ai` true and `ai_source` to `homepage`.
- Reuse the text the verifier already fetched. No extra HTTP call.

## Dashboard And Export

- Dashboard (spec 12): AI badge (see `ui-context.md`) and an "AI only" filter.
- Export (spec 10): "AI" column (Yes / No).
- Shared lookups (spec 13): copy `is_ai` and `ai_source`.

## Check When Done

- Migration runs and rolls back.
- `AiDetector` unit tests: each term matches, multi-word phrases match, "email", "maintain" and "html" do not match, empty input is false.
- Enrich job: name match sets `ai_source` to `name`. Homepage match sets `ai_source` to `homepage`. No match leaves `is_ai` false.
- `Http::fake()` records no extra request for the homepage AI check.
- Dashboard filter, badge, and export column work.
- Shared lookups copy `is_ai` and `ai_source`.
