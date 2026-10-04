# Testing Context

## Framework

Pest 5 with `pest-plugin-laravel`. Run with `php artisan test`, or `composer test` (Pint + Larastan + Pest), or `composer ci:check` (everything CI runs).

## Environment

- Tests use SQLite in memory and the `sync` queue (`phpunit.xml`). Local dev uses MySQL, so keep migrations portable (no MySQL-only column types or raw SQL).
- `phpunit.xml` pins values that would otherwise leak in from the real `.env` (for example `APP_URL=http://localhost`, while local `.env` uses `http://search_job.test`). If a test depends on a config value the real `.env` could change, pin it there with an `<env>` entry rather than relying on `.env`.
- Sponsor finder config used by tests (API keys, batch size, rate) is set in the test with `config([...])` or pinned in `phpunit.xml`, never read from the real `.env`.
- Feature tests use `RefreshDatabase` (already set in `tests/Pest.php`).
- Tests that render an Inertia page need a built frontend (`npm run build`). CI builds it first.

## Conventions

- Use `it()` / `test()` style, matching the starter kit tests.
- Feature tests live in `tests/Feature/Sponsors/`. Unit tests live in `tests/Unit/`, mirroring `app/`.
- Use factories for test data (`Sponsor::factory()`, `Team::factory()`, `User::factory()`).
- Name tests after behaviour: `it('skips a sponsor with no confident Companies House match')`.
- Assert Inertia pages with `assertInertia(fn ($page) => $page->component('sponsors/index'))`.

## External APIs

- Every external call is faked with `Http::fake()`. Call `Http::preventStrayRequests()` in tests that touch services, so a missed fake fails loudly.
- Sample API responses live in `tests/Fixtures/` (for example `tests/Fixtures/companies-house/search-match.json`).
- Use `Queue::fake()` to test that commands dispatch the right jobs.

## What Must Be Covered

- Pure logic: unit tests per rule and edge case for:
    - name normalisation (`CompanyName`)
    - employer classification (`EmployerClassifier`): SIC, keyword, noise keyword (including "property"), known employer by full register name ("SKY UK LIMITED" matches, "Sky Limited" does not), none, whole-word matching
    - term matching (`TermMatcher`): whole words and phrases, plural "s", hyphen and space forms, curly and straight apostrophes, no match inside other words
    - AI detection (`AiDetector`): name and text, phrases, short terms that must not match inside other words
    - rating parsing (`SponsorRating`): A, B, and unexpected values
    - priority mapping (`PriorityLocations`): case-insensitive towns, unlisted and empty towns
    - careers signal detection (`CareersSignals`): developer role terms found and not found (with plurals and hyphen forms), "node.js" matched but a plain "node" not, "phpunit" is not "php", visa offered, visa refused, refusal beating offer, right to work alone as not offered, "event sponsorship" as null
    - website scoring (`WebsiteScorer`)
- Each service: success, no match / empty result, and API error.
- Commands: happy path, bad input (missing file, missing header, unknown team), and filters.
- Import rating changes on re-import: A to B, B to A, B to A on a row skipped for another reason, no change, unparseable value keeps the stored grade, missing to B, and register grade replacing a hand-set one.
- Setting a missing rating in the dashboard: owner and admin allowed, member refused, register grade refused, invalid grade rejected.
- Shared lookups: `region`, `priority`, `rating_grade`, and `rating_grade_manual` are never copied, and `BRating` rows are never a source.
- The enrich job: every pipeline path listed in spec 08, plus running twice on a done row changes nothing.
- Every web route: happy path, validation failure, guest redirected to login, and a user from another team getting 404 or 403.

## Exit Criteria

A feature spec is not done until:

1. Its "Check When Done" list is covered by tests.
2. `composer ci:check` passes.
3. No test was skipped or marked incomplete to get there.
