# Status Summary and Second-Pass Backlog

All 15 feature specs are built. The second pass handles everything listed below. Add new items here as they come up, instead of fixing them on the spot. `progress-tracker.md` keeps the full history.

## Where Things Stand (2026-10-14)

- Done: all 15 features (setup, sponsors table, CSV import, Companies House, employer classification, web search, website scoring, enrich job, batch and schedule, export, sponsor edit permission, review dashboard, shared lookups, AI tagging, careers page and hiring signals).
- Next: the second pass below.

## Open Questions

Decide these before the second pass starts.

1. **Clearing and editing websites on the dashboard.** Should an editor be able to clear a wrong website, not only replace it? Should they be able to edit rows that are not `done`, for example a row skipped as not tech? Both are refused for now.
2. **Leading zeros in the export.** Excel strips the leading zeros of company numbers (for example `03917515`) when it opens the CSV. Should the export protect them, and how? A `="03917515"` cell works in Excel, but other tools show it as written.
3. **Search provider.** Which provider to use long term if Brave's limits are too tight.
4. **Hosting.** Where the app will run, if anywhere beyond local Herd. This decides what `deployment-context.md` has to cover (queue worker, scheduler, environment).

## Fixes and Additions for the Second Pass

### Check in a browser

- [ ] Team edit page: the `can_edit_sponsors` checkbox (Feature 11). So far it is covered only by type checks, the build and the page props test.
- [ ] Review dashboard `/sponsors` (Feature 12): filters, pagination, inline website edit, the confirmed checkbox, the rating select, and the permissions for each role. So far it is covered only by type checks, lint, the build and the page props tests.
- [ ] Team edit page: the "Share lookups with other teams" checkbox and the read-only text for members (Feature 13).
- [ ] Dashboard: the "Needs second check" badge and filter (Feature 13).
- [ ] Dashboard: the "AI" badge and "AI only" filter (Feature 14).
- [ ] Dashboard: the "Careers" and "Developer roles" columns, visa badges, and the two careers filters (Feature 15). The filter row now has six fields, so check it still fits.

### Matching gaps

- [ ] 8 register names use "t.a" for "trading as". They are not cut, because cutting "t.a" would break names like "I.T.A Tax Accounting". These 8 get no Companies House match. Find a safe rule, or accept the gap.
- [ ] 2 register names start with "T/A" and normalise to nothing, so they can never match.
- [ ] Brave results for small companies are mostly directory sites. Keep adding to `blocked_domains` as new directories show up in live runs.

### Shared lookups (Feature 13)

- [ ] Live run with two sharing teams, to see a real copy happen. Only one local team has data so far.
- [ ] If the `CompanyName` rules change (for example the "t.a" fix above), refill `sponsors.normalised_name` for every row. Shared lookups match on that stored column. A small command for it would make this a one-liner.
- [ ] The spec asks for a switch for lookup sharing. It is a checkbox, because a switch needs a new npm dependency (`@radix-ui/react-switch`). Decide whether to add it.

### AI tagging (Feature 14)

- [ ] False positives from "Ai" as a person's name or initials: "Ai Beauty LTD", "AI & CO LTD TA Flying Horse Restaurant" and others among the 299 register names tagged from the name. Options: only tag from the name when the row is also tech, or add AI noise words. Decide on a rule.
- [ ] Rows looked up before Feature 14 (about 60 local rows) have no AI tag. The name check can be re-run with no API calls; the homepage check needs the page fetched again.
- [ ] Live run to see homepage tagging on real sites.

### Careers pages (Feature 15)

- [ ] The careers check runs once per row and is never repeated. Jobs and visa wording change, so decide whether to re-check rows after some time (for example 30 days).
- [ ] A fetch error counts as "no page", so a site that is down during the check is marked checked with nothing found. Decide whether to retry those later.
- [ ] Careers pages built with JavaScript (many job boards) may show little text to a plain fetch, so developer roles and visa wording can be missed.
- [ ] Larger live run: only 7 local rows were eligible (5 pages found).

### Revisit if things change

- [ ] Priority locations are one global config list. Make them per team only if a team outside Yorkshire uses the app.

### Follow from the open questions

- [ ] Whatever is decided for questions 1 to 4 above: code, tests, spec updates and context updates.

### Before calling the project done

- [ ] Run `composer ci:check` in full and record the test count. The last count is 753, from Feature 15.
- [ ] Do a full live run on one region with real keys, from import to enrich to export, and check the results by hand.
