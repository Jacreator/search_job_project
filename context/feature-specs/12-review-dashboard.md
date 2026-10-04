# Review Dashboard

Build the review page inside the logged-in app. Follow `ui-context.md`.

## Routes

Inside the existing `{current_team}` route group (`auth`, `verified`, `EnsureTeamMembership`), with scoped bindings:

- `GET /{current_team}/sponsors` - sponsor table with filters and summary counts.
- `PATCH /{current_team}/sponsors/{sponsor}` - update website and confirmed flag.
- `PATCH /{current_team}/sponsors/{sponsor}/rating` - set a missing rating grade.

## Page

- React page `resources/js/pages/sponsors/index.tsx`, using `AppLayout`.
- "Sponsors" item in the sidebar nav.
- Summary counts per status at the top, and skip counts per skip reason.
- Filters: status, town, region, minimum confidence, tech only, name search.
- Paginated table, 50 per page, filters kept in the query string.
- Table includes a region column.
- Inline edit of website (validated as an http/https URL) and a confirmed checkbox.
- Saving a website by hand sets confidence to 100 and confirmed to true.
- A rating column shows A, B, or "Missing".

## Missing Rating

- Owners and admins can set the grade to A or B when it is missing, or change a grade they set by hand (`rating_grade_manual` true). Members cannot, even with the edit flag.
- Grades that came from the register cannot be changed here.
- Saving sets `rating_grade` and `rating_grade_manual` to true, then applies `RatingChange::apply` (spec 03), so B skips the row with `BRating` and A returns a `BRating` row to `pending`.
- Owners and admins see a select (A or B) on editable rows. Everyone else sees the grade as text.
- Filter: "Rating missing".

## Added By Later Specs

These are not part of this spec. Each later spec extends the dashboard when it is built:

- Spec 13: "Needs second check" badge and filter.
- Spec 14: AI badge and an "AI only" filter.
- Spec 15: careers URL link, a "Developer roles" column, "Visa sponsorship offered" and "No visa sponsorship" badges, and filters for developer roles and visa sponsorship.

## Rules

- Only the current team's sponsors are listed or editable.
- Editing requires `User::canEditSponsors($team)` (spec 11). Users without it see the table read-only (no edit controls).
- Form request for validation and authorisation (`SponsorPolicy`). Setting a rating uses its own Form Request and `SponsorPolicy::setRating`: owner or admin of the team, and the grade is missing or hand-set. Validates the grade as `A` or `B`.
- Controller stays thin.
- Use Wayfinder route helpers in the page.

## Check When Done

- Feature tests for listing, filtering (including region), and updating.
- Skip counts by reason are shown.
- Guests are redirected to login.
- A user cannot see or update another team's sponsor.
- A member without the edit flag can view but gets 403 on update. Owner, admin, and flagged members can update.
- Owner and admin can set a missing grade. B skips the row with `BRating`, A leaves the status alone (or returns a `BRating` row to `pending`).
- Owner and admin can change a hand-set grade, but get 403 on a grade from the register.
- A member, with or without the edit flag, gets 403 when setting a grade.
- An invalid grade (not A or B) fails validation.
- The "Rating missing" filter works.
- `npm run types:check` and `npm run check` pass.
