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
  - `routes` (array of worker routes to import, from `SPONSOR_ROUTES` as a comma-separated list, defaulting to the four routes in `project-overview.md`)
  - `tech_sic_codes` (see below)
  - `tech_keywords` (see below)
  - `noise_keywords` (see below)
  - `known_employers` (see below)
  - `priority_locations` (see below)
- Add the env keys to `.env.example` with empty values (route list may be left out so the default applies).
- Create `storage/app/imports` and `storage/app/exports`, each with a `.gitignore` that ignores everything except itself.
- Set `APP_NAME` to "Sponsor Finder" in `.env.example`.

## Tech SIC Codes

`62011, 62012, 62020, 62030, 62090, 63110, 63120, 58210, 58290, 72190`

Store them as strings (SIC codes from Companies House are strings).

## Tech Keywords

`software, tech, technology, technologies, digital, data, ai, cloud, cyber, analytics, computing, devops, saas, fintech, web, apps, developer`

## Noise Keywords

`care, nursing, food, restaurant, construction, cleaning, recruitment, beauty, logistics, transport, dental, pharmacy, school, property`

## Known Employers

Starter list, written as the names appear on the sponsor register:

`Sky UK Limited, BT Group, Barclays Bank UK PLC, Lloyds Bank plc, HSBC Holdings plc, NatWest Group PLC, Santander UK plc, Nationwide Building Society, Yorkshire Building Society, Leeds Building Society, Skipton Building Society, Jet2.com, Asda Stores Ltd, Wm Morrison Supermarkets Limited, Tesco Stores Limited, J Sainsbury Plc, Marks and Spencer Group Plc, British Broadcasting Corporation, Rightmove Group Ltd, Capita plc, Accenture (UK) Limited, Capgemini UK plc, Deloitte LLP, PricewaterhouseCoopers LLP, Ernst & Young, KPMG LLP, Infosys Limited, Tata Consultancy Services, Wipro Limited, Cognizant Worldwide Limited`

Every name was checked against the 2026-10-02 register CSV (10 were respelled on 2026-10-07 to match it). Check new names against the latest register before adding them. Put the same note as a comment above the list in `config/sponsor-finder.php`.

Matching follows spec 05: the full register name must equal an entry, ignoring only letter case and extra spaces. Suffixes such as "Limited" or "plc" are not removed, so each entry must be the exact register name.

## Priority Locations

A map of town to region and priority, for example `'Sheffield' => ['region' => 'Yorkshire', 'priority' => 100]`.

| Town         | Region        | Priority |
| ------------ | ------------- | -------- |
| Sheffield    | Yorkshire     | 100      |
| Rotherham    | Yorkshire     | 90       |
| Doncaster    | Yorkshire     | 90       |
| Barnsley     | Yorkshire     | 90       |
| Chesterfield | East Midlands | 85       |
| Leeds        | Yorkshire     | 80       |
| Wakefield    | Yorkshire     | 80       |
| Bradford     | Yorkshire     | 75       |
| Huddersfield | Yorkshire     | 75       |
| York         | Yorkshire     | 70       |
| Hull         | Yorkshire     | 70       |
| Manchester   | North West    | 60       |

Towns not in the map get region null and priority 0.

## Check When Done

- `composer ci:check` passes.
- `config('sponsor-finder.batch_size')` returns 100.
- A small test asserts the config defaults (batch size, rate, routes, SIC codes, tech and noise keywords, Sheffield priority 100).
- The config test asserts `known_employers` is not empty and contains "Sky UK Limited".
