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

`App\Services\WebsiteVerifier::mentions(string $url, string $name): bool`

- Fetch the homepage with a 10 second timeout.
- Strip tags, lowercase, look for the first word of the normalised name.
- Any exception returns false.

If the verifier returns true, add 25 to the score (cap 100).

## Check When Done

- Unit tests for scoring with real-looking examples (FourJaw, The Floow, Sumo Digital).
- Blocked domains are never returned.
- Verifier test with `Http::fake()`.
