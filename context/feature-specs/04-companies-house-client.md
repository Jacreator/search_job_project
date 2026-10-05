# Companies House Client

Build the API client only. Do not wire it into a job yet.

## Service

`App\Services\CompaniesHouse` with:

- `findCompany(string $name): ?CompanyMatch` - calls `GET /search/companies?q=...&items_per_page=5`, returns the first result whose normalised title equals the normalised sponsor name. Returns null if none match.
- `profile(string $companyNumber): CompanyProfile` - calls `GET /company/{number}`, returns status and SIC codes.

Base URL: `config('sponsor-finder.companies_house_url')`, from `COMPANIES_HOUSE_URL` (trimmed, no trailing slash). Empty means `https://api.company-information.service.gov.uk`. `phpunit.xml` pins it empty.
Auth: HTTP basic, API key as username, empty password.

## Errors (decided 2026-10-05)

- "No match" and "API error" are different: no matching result returns null, an API problem throws `App\Exceptions\CompaniesHouseRequestFailed`, so the enrich job (spec 08) retries errors and only skips real no-matches.
- Throws on: no `COMPANIES_HOUSE_KEY` (before any request), any non-2xx response, a connection failure, or a profile with no `company_number`. Messages name the endpoint and HTTP status only, never the key or headers, because the job saves them on failed rows.
- Every call uses a 10 second timeout and `retry(2, 500)`: two attempts in total. Only connection errors, 429, and 5xx are retried. 401 and 404 are not. The job's own retries (spec 08) cover longer outages.
- Search results missing a `title` or `company_number` are skipped. A profile with no `sic_codes` gives an empty list, no `company_status` gives null, and non-string SIC codes are dropped.
- A sponsor name that normalises to nothing returns null without a request.
- `CompanyMatch` and `CompanyProfile` live in `App\Services`. `CompanyProfile::$status` is nullable.

## Name Normalisation

`App\Support\CompanyName::normalise(string): string`

- Lowercase.
- Drop anything after "t/a" or "trading as".
- Remove: limited, ltd, plc, llp, uk, the, group, holdings.
- Remove punctuation, collapse spaces. Apostrophes (straight and curly) are removed without a space ("Sainsbury's" gives "sainsburys"); other punctuation becomes a space ("Jet2.com" gives "jet2 com").
- Words are removed only when whole ("Groupon" keeps "groupon"). "t/a" also matches with spaces around the slash ("T / A") and as "t/as" or "t/a's" (403 register names use these). "t.a" and "trading name" are not treated as trading names, because "t.a" appears inside real names such as "I.T.A Tax Accounting".

## Value Objects

- `CompanyMatch` (number, title)
- `CompanyProfile` (number, status, sicCodes array)

## Check When Done

- Unit tests for normalisation (at least 8 cases, including "t/a" names).
- `Http::fake()` tests for match found, no match, and API error.
