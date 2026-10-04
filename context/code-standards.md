# Code Standards

## General

- Keep classes small and single-purpose.
- Fix root causes. Do not layer workarounds.
- Respect the boundaries in `architecture-context.md`.
- Name classes after what they do, not the technology.
- Match the style of the surrounding starter kit code.

## PHP

- `declare(strict_types=1);` in every new PHP file. Do not add it to existing starter kit files.
- Typed properties, parameters, and return types everywhere.
- Constructor injection for services. No facades inside services except `Http`.
- Use readonly value objects (for example `CompanyMatch`, `WebsiteCandidate`) instead of loose arrays when passing data between services.
- No `env()` calls outside `config/` files.
- Larastan runs at level 7. Fix the type, do not add ignores. Use a `@var` or `@return` docblock only when it gives accurate information.

## Laravel

- Commands stay thin: parse options, dispatch, report.
- Jobs implement `ShouldQueue`, set `$tries`, `$backoff`, and use the `RateLimited` middleware.
- Use `Http::timeout()` and `->retry()` on every outbound call.
- Use `lazy()` or `chunkById()` for large queries. Never `all()` on `sponsors`.
- Use enums for fixed value sets (`App\Enums\SponsorStatus`, `TechReason`, `SkipReason`, `VisaSponsorship`).
- Controllers stay thin. Validation and authorisation go in a Form Request.
- Load sponsors through the current team (for example `$team->sponsors()`), never `Sponsor::find()` in a web request.

## External APIs

- Each API lives behind one service class.
- Web search sits behind an interface (`WebSearch`) so the provider can be swapped.
- Validate API responses before using them. Treat missing fields as no match.

## Frontend

- TypeScript strict mode. No `any`. Type page props explicitly.
- Use shadcn components from `@/components/ui` and Lucide icons.
- Use Wayfinder route helpers (`@/routes`, `@/actions`) for URLs.
- `npm run check` and `npm run types:check` must pass.

## Testing

- Pest for all tests. See `testing-context.md`.
- `Http::fake()` for every external call. Store sample responses in `tests/Fixtures/`.

## Style

- Laravel Pint with the `laravel` preset (`pint.json`).
- Short comments only where the reason is not obvious.
