# Review Dashboard

Build the review page inside the logged-in app. Follow `ui-context.md`.

## Routes

Inside the existing `{current_team}` route group (`auth`, `verified`, `EnsureTeamMembership`), with scoped bindings:

- `GET /{current_team}/sponsors` - sponsor table with filters and summary counts.
- `PATCH /{current_team}/sponsors/{sponsor}` - update website and confirmed flag.

## Page

- React page `resources/js/pages/sponsors/index.tsx`, using `AppLayout`.
- "Sponsors" item in the sidebar nav.
- Summary counts per status at the top.
- Filters: status, town, minimum confidence, tech only, name search.
- Paginated table, 50 per page, filters kept in the query string.
- Inline edit of website (validated as an http/https URL) and a confirmed checkbox.
- Saving a website by hand sets confidence to 100 and confirmed to true.

## Rules

- Only the current team's sponsors are listed or editable.
- Editing requires `User::canEditSponsors($team)` (spec 11). Users without it see the table read-only (no edit controls).
- Form request for validation and authorisation (`SponsorPolicy`).
- Controller stays thin.
- Use Wayfinder route helpers in the page.

## Check When Done

- Feature tests for listing, filtering, and updating.
- Guests are redirected to login.
- A user cannot see or update another team's sponsor.
- A member without the edit flag can view but gets 403 on update. Owner, admin, and flagged members can update.
- `npm run types:check` and `npm run check` pass.
