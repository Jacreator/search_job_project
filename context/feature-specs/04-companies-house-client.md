# Companies House Client

Build the API client only. Do not wire it into a job yet.

## Service

`App\Services\CompaniesHouse` with:

- `findCompany(string $name): ?CompanyMatch` - calls `GET /search/companies?q=...&items_per_page=5`, returns the first result whose normalised title equals the normalised sponsor name. Returns null if none match.
- `profile(string $companyNumber): CompanyProfile` - calls `GET /company/{number}`, returns status and SIC codes.

Base URL: `https://api.company-information.service.gov.uk`
Auth: HTTP basic, API key as username, empty password.

## Name Normalisation

`App\Support\CompanyName::normalise(string): string`

- Lowercase.
- Drop anything after "t/a" or "trading as".
- Remove: limited, ltd, plc, llp, uk, the, group, holdings.
- Remove punctuation, collapse spaces.

## Value Objects

- `CompanyMatch` (number, title)
- `CompanyProfile` (number, status, sicCodes array)

## Check When Done

- Unit tests for normalisation (at least 8 cases, including "t/a" names).
- `Http::fake()` tests for match found, no match, and API error.
