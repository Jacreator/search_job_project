# Public Company Directory

**Status: draft, awaiting James's approval.**

A public, searchable list of sponsors at `/companies`, with no login. The data comes from one owner team chosen in config, and only public safe fields are ever sent to the browser. Builds on specs 12, 14, 15, and 23 (sector).

## Config

Add to `config/sponsor-finder.php`, in a new "Public Directory" section:

- `public_team` (`SPONSOR_PUBLIC_TEAM`, a team slug, trimmed, empty means null). Unset, or a slug with no team (including a soft-deleted team): every public page returns 404.
- `public_rate_per_minute` (`SPONSOR_PUBLIC_RATE_PER_MINUTE`, default 60): requests per minute per IP address for the public routes.

Add both keys to `.env.example` (empty), and pin them in `phpunit.xml`.

Renaming the owner team changes its slug (starter kit behaviour), so the directory returns 404 until `SPONSOR_PUBLIC_TEAM` is updated. This is written in `deployment-context.md`.

## Migration

`add_public_id_to_sponsors_table`:

- `public_id` (char(26), nullable, unique): a ULID, used in public URLs instead of the numeric id.
- The migration fills it for every existing row (`chunkById`, one `Str::ulid()` per row). 122,938 local rows, so expect a short wait, like the spec 13 `normalised_name` fill.
- New rows get one from the `Sponsor` model's `creating` hook. `RegisterImport` inserts rows without model events, so it sets `public_id` itself, as it does for `normalised_name`.

### Why A ULID, Not A Slug

- It never changes. A slug from the name breaks links when a company is renamed or re-imported with a new spelling.
- It never collides. The register has many companies with the same name in different towns, so a name slug needs extra parts anyway.
- It cannot be guessed or counted, unlike the numeric id, so the URL says nothing about how many rows there are or which team owns them.
- `whereUlid` gives a strict route constraint for free (see Routes).

## Who Is Listed

A sponsor is publicly listed when all of these are true:

- It belongs to the public team.
- Its status is `done`, or `skipped` with `NotTech`: a company that matched on Companies House and is active. Pending, failed, `NoMatch`, `Inactive`, and `BRating` rows are never listed.
- It has a `public_id`.

This is one scope, `Sponsor::publiclyListed()`, used by both public pages (spec 25 too).

## Public Safe Fields

Only these are ever sent to the browser:

| Field              | Rule                                                                                           |
| ------------------ | ---------------------------------------------------------------------------------------------- |
| `id`               | The `public_id`, never the numeric id                                                          |
| `name`, `town`     | As stored                                                                                      |
| `region`           | As stored                                                                                      |
| `sector`           | Section letter and name (spec 23)                                                              |
| `routes`           | The register routes, as a list                                                                 |
| `rating`           | The rating grade (`A`), or null                                                                |
| `website`          | Only when `confirmed`, or `confidence` 75 or more. Otherwise null                              |
| `careers_url`      | Only when the website is shown. Otherwise null                                                 |
| `developer_roles`  | `mentions_developer_roles`, only when the website is shown. Otherwise null                     |
| `visa_sponsorship` | `offered`, `not_offered`, or null, only when the website is shown. Otherwise null              |
| `is_ai`            | True when tagged from the name, or from the homepage and the website is shown. Otherwise false |

The careers fields and a homepage AI tag all come from the website, so they are hidden whenever the website is hidden. That way a doubtful website never shows up indirectly.

Never sent: confidence, `confirmed`, `needs_second_check`, `status`, `skip_reason`, `attempts`, `error`, `priority`, `tech_reason`, `ai_source`, `rating_grade_manual`, `company_number` (spec 25 shows it on the company page only), timestamps (spec 25 decides), `team_id`, or any team information.

## Code

Kept apart from the team dashboard, so internal fields cannot leak by accident:

- `App\Services\PublicDirectory`: `team(): ?Team` (the config team or null), `companies(PublicCompanyFilters): LengthAwarePaginator`, `sectors(): array`, `regions(): array`. It selects only the columns the public fields need, so internal columns are never even loaded.
- `App\Services\PublicCompanyFilters` (readonly value object): search, town, region, sector, `aiOnly`, `visaOffered`, `developerRoles`.
- `App\Http\Controllers\PublicDirectory\CompanyController` (`index` here, `show` in spec 25).
- `App\Http\Requests\PublicDirectory\ListCompaniesRequest`: validates the filters, `authorize()` true.
- `App\Http\Resources\PublicCompanyResource`: the only place the public fields are built, with the rules in the table above. Explicit keys only, never `toArray()` of the model.

## Routes

In `routes/web.php`, before the `{current_team}` group:

- `GET /companies` (`companies.index`) and `GET /companies/{company}` (`companies.show`, spec 25), with `{company}` constrained by `whereUlid`.
- Middleware `throttle:public-directory`. The `public-directory` limiter (per IP, `public_rate_per_minute`) is registered in `AppServiceProvider`.
- No `auth` middleware.

Registering them first, with the ULID constraint, keeps them apart from the team routes: `/companies/sponsors` still goes to a team whose slug is "companies", because "sponsors" is not a ULID.

## Search And Filters

- Search by name (`search`): case-insensitive "contains", escaped like the dashboard search.
- Town (`town`, text, case-insensitive), region (`region`, select from the public team's listed regions), sector (`sector`, select from the listed sectors).
- AI only (`ai`), visa offered (`visa_offered`), has developer roles (`developer_roles`), each a checkbox. These apply the same "only when the website is shown" rules, so a filter never finds a row by a hidden value.
- Sorted by name, then `public_id`. 50 per page. Filters stay in the query string.
- No export, no JSON endpoint, no per page option.

## Page

- `resources/js/pages/companies/index.tsx`, with parts in `resources/js/components/companies`.
- A new `resources/js/layouts/public-layout.tsx`: a simple header with the app name and a "Log in" link (or "Dashboard" when logged in), no sidebar, and a footer with the attribution "Contains public sector information licensed under the Open Government Licence v3.0." (the register and Companies House data are published under that licence). `app.tsx` uses it for `companies/` pages.
- A list (or table) of companies: name (linking to the spec 25 page), town, region, sector, rating, badges for AI, "Visa sponsorship offered", and "Developer roles", and the website link (new tab, `rel="noopener noreferrer"`).
- The filter form and pagination work like the dashboard's. An empty state when nothing matches.

## Rules

- Public pages never read the user's current team. A logged-in user sees the same page as a guest.
- Inertia's shared props for a guest carry no sponsor or team data. Tests check this.
- React escapes all output. No `dangerouslySetInnerHTML`.

## Check When Done

- Migration runs and rolls back. Every existing row gets a unique ULID. New rows from the model and from import get one.
- No public team set, an unknown slug, and a soft-deleted team: `/companies` returns 404.
- Guests can see the page with no login. A logged-in user from another team sees the same rows.
- Only rows that are publicly listed appear: tests for each excluded status and skip reason, and for rows of another team.
- Each row has exactly the public field keys, and none of the internal ones (confidence, `confirmed`, `needs_second_check`, `status`, `skip_reason`, `attempts`, `error`, `priority`, `team_id`, numeric id). Checked on the page props, including the shared props.
- Website shown when confirmed, shown at confidence 75, hidden at 74 and unconfirmed. Careers fields and homepage AI tags hidden with it. Name AI tags always shown.
- Every filter and a combination, invalid filter values rejected, the hidden-value rule for the AI, visa, and developer role filters.
- Sorted by name, 50 per page, filters kept across pages.
- The throttle: the 61st request in a minute gets 429.
- `/companies/sponsors` still reaches a team called "companies".
- `project-overview.md`, `architecture-context.md`, `security-context.md`, `ui-context.md`, `data-model-context.md`, and `deployment-context.md` updated.
