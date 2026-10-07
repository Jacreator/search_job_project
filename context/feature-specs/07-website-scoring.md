# Website Scoring and Verification

Turn search results into one website with a confidence score.

## Scorer

`App\Services\WebsiteScorer::best(string $name, array $results): ?WebsiteCandidate`

Skip any result whose host matches a blocked domain (`blocked_domains` in `config/sponsor-finder.php`):
linkedin, facebook, twitter, x.com, instagram, youtube, wikipedia, company-information.service.gov.uk, gov.uk, endole, opencorporates, crunchbase, dealroom, prospeo, glassdoor, indeed, reed.co.uk, yell.com, bloomberg, zoominfo, rocketreach.

The match is on the host, not the whole URL, so `x.com` does not block `fedex.com` and a blocked word in the path does not count. An entry with a dot (`x.com`, `reed.co.uk`) blocks that domain and its subdomains. An entry without a dot (`linkedin`, `cylex`) blocks any host that contains it.

Added from live checks (2026-10-07):

| Domain                                                                                                                                     | Seen as                                                 |
| ------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------- |
| cylex, misterwhat, companycheck                                                                                                            | Business directories (feature 06 check on IDAQ LIMITED) |
| lursoft, companiesintheuk, dnb.com, kompass, 192.com, thomsonlocal, nextdoor, mapcarta, mapquest, maps.apple.com, automapa, azure-book.com | Company data, local directories, and maps               |
| licensed-sponsors-uk, checkall, immigrationgpt, huntukvisasponsors, sponsorlicensechecker, ukvisasponsorshipchecker, ukmovecheck, visapath | Sites that list the sponsor register                    |
| restaurantguru, halalresmenu, wheree, hungryfoody, halalguide, www.nhs.uk, cqc.org.uk, carechoices.co.uk, autumna, dentalchoices, ouch.ai  | Restaurant, care, and dental listings                   |
| companyjobs                                                                                                                                | Job board                                               |

`maps.apple.com` and `www.nhs.uk` are exact, so Apple's and NHS trusts' own sites are not blocked. Property portals (Rightmove, Zoopla) are not blocked, because Rightmove is a known employer.

Scoring (cap at 100), using `CompanyName::normalise()` on the name:

- Full normalised name (no spaces) inside the host, ignoring hyphens (`sumo-digital.com` holds `sumodigital`): +60
- Else first word of the name inside the host: +40. A first word shorter than 3 characters must start a host label (`a1sheffieldtaxis.co.uk` matches "A1", `digital-sheffield.co.uk` does not match "IT").
- First word as a whole word in the normalised result title: +15

Return the homepage (`scheme://host`, host lowercased) of the highest scorer as a `WebsiteCandidate` (url, score). Ties go to the earlier search result. Return null when no unblocked result scores above 0.

## Verifier

`App\Services\WebsiteVerifier::verify(string $url, string $name): VerifiedPage`

`VerifiedPage` is a readonly value object with:

- `mentionsName` (bool)
- `text` (string): the stripped, lowercased page text, capped at 200 KB. Empty string when the fetch failed.

Rules:

- Fetch the homepage with a 10 second timeout. Follow the outbound request rules in `security-context.md`.
- Redirects are followed by hand (up to 5), each hop checked first. Every address of the host must be public (no private, loopback, link-local, or reserved ranges), and the request is pinned to the checked address. `App\Services\HostResolver` does the DNS lookup, so tests can replace it.
- A page over 2 MB is aborted and counts as a failed fetch. A non-2xx response also counts as failed.
- Guzzle's default user agent is used. A live check found a site that refused a custom one with 403.
- Remove scripts, styles, and comments, strip tags, decode entities, collapse spaces, lowercase. Look for the first word of the normalised name as a whole word, with apostrophes dropped as `CompanyName` does.
- Any exception returns `mentionsName` false and empty `text`.
- The text is returned so later steps (AI tagging, spec 14) can reuse it without a second HTTP call. It is not stored.

If `mentionsName` is true, add 25 to the score (cap 100): `WebsiteScorer::confidence(WebsiteCandidate, VerifiedPage): int`.

## Check When Done

- Unit tests for scoring with real-looking examples (FourJaw, The Floow, Sumo Digital).
- Blocked domains are never returned.
- Verifier tests with `Http::fake()`: name found, name not found, request error.
- Verifier text is capped at 200 KB.
