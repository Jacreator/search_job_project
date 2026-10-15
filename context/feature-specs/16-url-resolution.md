# URL Resolution

**Status: draft, awaiting James's approval.**

Fix how relative URLs are turned into absolute ones, and do it in one place. Builds on specs 07 and 15.

## Problem

`PageFetcher::resolveLocation()` (redirects) treats every relative `Location` as starting at the site root:

- `Location: careers` from `https://acme.test/about/` goes to `https://acme.test/careers`, not `https://acme.test/about/careers`.
- `Location: ?page=2` from `https://acme.test/jobs` goes to `https://acme.test/?page=2`, dropping the path.
- `./` and `../` segments are kept as written.

`CareersPageFinder::resolve()` (homepage links) has its own, separate logic that handles relative paths but not `./` or `../`.

## Decisions For Approval

1. One pure helper, `App\Support\Url::resolve(string $base, string $reference): ?string`, used by both `PageFetcher` and `CareersPageFinder`. Their private resolvers are removed.
2. It follows the RFC 3986 rules for http and https only. Anything else (`mailto:`, `tel:`, `javascript:`, `ftp:`) gives null.

## Rules

- Absolute `http://` or `https://` reference: returned as is.
- Scheme-relative (`//host/path`): the base's scheme is added.
- Absolute path (`/careers`): the base's origin plus the path.
- Query only (`?page=2`): the base's origin and path plus the new query.
- Fragment only (`#jobs`): the base without its fragment.
- Relative path (`careers`, `./careers`, `../careers`): merged with the base's directory, then `.` and `..` segments removed. `..` never goes above the root.
- Fragments are dropped from the result. The fetcher never sends them, and the careers finder compares URLs without them.
- An empty reference gives the base without its fragment. A base that is not http or https gives null.

## Check When Done

- `tests/Unit/Support/UrlTest.php`: every rule above, including `careers` from `/about/`, `?page=2` from `/jobs`, `../careers` from `/a/b/`, `../../../careers` staying at the root, `//cdn.test/x`, `mailto:`, `javascript:`, and an empty reference.
- `PageFetcher` follows a relative redirect (`Location: careers`) and a query-only redirect to the right address (`Http::fake()`).
- The existing verifier and careers finder tests pass unchanged.
- `architecture-context.md` lists `Url` under `app/Support`.
