# AI Tagging

Tag sponsors that look like AI companies, so AI Engineer roles are easy to find. Builds on the enrich job (spec 08) and the verifier (spec 07). Also adds the shared term matcher that spec 15 reuses.

## Migration

`add_ai_fields_to_sponsors_table`:

- `is_ai` (boolean, default false, indexed)
- `ai_source` (string, nullable: `name` or `homepage`)

Add casts and fillable entries on `Sponsor`.

## Config

Add `ai_keywords` to `config/sponsor-finder.php`:

`ai, artificial intelligence, machine learning, ml, deep learning, llm, large language model, genai, generative, computer vision, nlp, data science, robotics, mlops, ai-powered`

## Term Matcher

`App\Support\TermMatcher::contains(string $text, array $terms): bool`, a pure helper used by `AiDetector` here and `CareersSignals` in spec 15.

Before matching, both the text and each term are prepared the same way:

- Lowercase.
- Curly apostrophes become straight ones, so "we’re" and "we're" match.
- Hyphens become spaces, so "ai-powered" matches "ai powered" and "full-stack" matches "full stack".
- Repeated spaces collapse to one.

Then a term matches when it appears as a whole word or whole phrase, optionally followed by "s":

- "developer" matches "developers", and "llm" matches "LLMs".
- "ai" does not match "email" or "maintain", and "ml" does not match "html".
- Dots inside a term are literal, so "node.js" matches only "node.js".

## Class

`App\Support\AiDetector` with two pure methods:

- `fromName(string $name): bool` - true when the normalised name contains an AI term.
- `fromText(string $text): bool` - true when the page text contains an AI term.

Both use `TermMatcher` with `ai_keywords`. No database or HTTP.

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
- `TermMatcher` unit tests:
  - whole word and whole phrase match
  - plural "s" matches ("developers", "LLMs")
  - hyphen and space forms match each other ("ai-powered" and "ai powered", "full-stack" and "full stack")
  - curly and straight apostrophes match ("we’re hiring" and "we're hiring")
  - no match inside other words ("email", "maintain", "html")
  - "node.js" matches, "node" alone does not
  - empty text or empty term list is false
- `AiDetector` unit tests: each term matches, including "genai", "large language model", "mlops", and "AI-powered".
- Enrich job: name match sets `ai_source` to `name`. Homepage match sets `ai_source` to `homepage`. No match leaves `is_ai` false.
- `Http::fake()` records no extra request for the homepage AI check.
- Dashboard filter, badge, and export column work.
- Shared lookups copy `is_ai` and `ai_source`.
