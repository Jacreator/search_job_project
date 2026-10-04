# Security Context

## Authentication

- Provided by the starter kit (Fortify): login, registration, email verification, password reset, two-factor, and passkeys. Keep as is.
- The sponsor dashboard sits inside the `auth` + `verified` + `EnsureTeamMembership` route group.

## Team Scoping (authorisation)

- A user can only see and change sponsors of a team they belong to.
- Routes live under `/{current_team}/...`, so `EnsureTeamMembership` rejects non-members with 403.
- Controllers resolve sponsors through the team (`$team->sponsors()`), so a sponsor ID from another team returns 404. Use scoped route model binding (`->scopeBindings()`) for `/{current_team}/sponsors/{sponsor}`.
- Updating a sponsor is checked in the Form Request's `authorize()` via a `SponsorPolicy`: the sponsor's team must be one the user belongs to, and `User::canEditSponsors($team)` must be true.
- Who may edit sponsors (spec 11): owners and admins always; members only when an owner or admin has switched their `can_edit_sponsors` flag on (default off). Every member can view.

## Input Handling

- All web input goes through a Form Request.
- Websites entered by hand are validated as `http` or `https` URLs only.
- CSV import treats every cell as untrusted text: trim it, cap its length to the column size, and never evaluate it.
- Exported CSV cells that start with `=`, `+`, `-`, or `@` are prefixed with `'` so Excel does not run them as formulas.
- React escapes output by default. Never use `dangerouslySetInnerHTML` for sponsor data.

## Outbound Requests

- The website verifier (spec 07) and the careers job (spec 15) fetch URLs that came from search results or from the sponsor's own pages. Only fetch `http` / `https` URLs, use a 10 second timeout, cap the response size (page text is capped at 200 KB), and do not follow redirects to private or local IP ranges.

## Secrets

- `COMPANIES_HOUSE_KEY` and `BRAVE_SEARCH_KEY` live only in `.env`. They are read in `config/sponsor-finder.php` and never committed, logged, or sent to the frontend.
- `.env.example` lists the keys with empty values.
- Error messages saved on a failed sponsor must not include API keys or full request headers.

## Data

- The register CSV in `storage/app/imports/` and the exports in `storage/app/exports/` are git-ignored.
