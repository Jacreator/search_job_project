# Website Scoring and Verification

Turn search results into one website with a confidence score.

## Scorer

`App\Services\WebsiteScorer::best(string $name, array $results): ?WebsiteCandidate`

Skip any result whose URL contains a blocked domain:
linkedin, facebook, twitter, x.com, instagram, youtube, wikipedia, company-information.service.gov.uk, gov.uk, endole, opencorporates, crunchbase, dealroom, prospeo, glassdoor, indeed, reed.co.uk, yell.com, bloomberg, zoominfo, rocketreach.

Scoring (cap at 100):

- Full normalised name (no spaces) inside the host: +60
- Else first word of the name inside the host: +40
- First word inside the result title: +15

Return the homepage (`scheme://host`) of the highest scorer.

## Verifier

`App\Services\WebsiteVerifier::verify(string $url, string $name): VerifiedPage`

`VerifiedPage` is a readonly value object with:

- `mentionsName` (bool)
- `text` (string): the stripped, lowercased page text, capped at 200 KB. Empty string when the fetch failed.

Rules:

- Fetch the homepage with a 10 second timeout. Follow the outbound request rules in `security-context.md`.
- Strip tags, lowercase, look for the first word of the normalised name.
- Any exception returns `mentionsName` false and empty `text`.
- The text is returned so later steps (AI tagging, spec 14) can reuse it without a second HTTP call. It is not stored.

If `mentionsName` is true, add 25 to the score (cap 100).

## Check When Done

- Unit tests for scoring with real-looking examples (FourJaw, The Floow, Sumo Digital).
- Blocked domains are never returned.
- Verifier tests with `Http::fake()`: name found, name not found, request error.
- Verifier text is capped at 200 KB.
