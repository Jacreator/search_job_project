# Project Setup

The Laravel React starter kit is already installed (Laravel 13, PHP 8.3+, Pest, Pint, Larastan, database queue with jobs and failed jobs tables). Add the sponsor finder's base configuration only. No features yet.

## Already Done (starter kit)

- Laravel 13 project, PHP 8.3+.
- Pest and Laravel Pint installed.
- `QUEUE_CONNECTION=database`, jobs and failed jobs tables migrated.

## Tasks

- Create `config/sponsor-finder.php` with:
  - `companies_house_key` (`COMPANIES_HOUSE_KEY`)
  - `brave_key` (`BRAVE_SEARCH_KEY`)
  - `batch_size` (`SPONSOR_BATCH_SIZE`, default 100)
  - `rate_per_minute` (`SPONSOR_RATE_PER_MINUTE`, default 30)
  - `routes` (array of worker routes to import, from `SPONSOR_ROUTES` as a comma-separated list, defaulting to the three routes in `project-overview.md`)
  - `tech_sic_codes` (see below)
- Add the keys to `.env.example` with empty values (route list may be left out so the default applies).
- Create `storage/app/imports` and `storage/app/exports`, each with a `.gitignore` that ignores everything except itself.
- Set `APP_NAME` to "Sponsor Finder" in `.env.example`.

## Tech SIC Codes

`62011, 62012, 62020, 62030, 62090, 63110, 63120, 58210, 58290, 72190`

Store them as strings (SIC codes from Companies House are strings).

## Check When Done

- `composer ci:check` passes.
- `config('sponsor-finder.batch_size')` returns 100.
- A small test asserts the config defaults (batch size, rate, routes, SIC codes).
