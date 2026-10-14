# Status Summary and Second-Pass Backlog

The plan is to finish features 13 to 15 first, then do a second pass that handles everything listed below. Add new items here as they come up while 13 to 15 are being built, instead of fixing them on the spot. `progress-tracker.md` keeps the full history.

## Where Things Stand (2026-10-14)

- Done: features 01 to 14 (setup, sponsors table, CSV import, Companies House, employer classification, web search, website scoring, enrich job, batch and schedule, export, sponsor edit permission, review dashboard, shared lookups, AI tagging).
- Next: 15 Careers page and hiring signals.

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

### Revisit if things change

- [ ] Priority locations are one global config list. Make them per team only if a team outside Yorkshire uses the app.

### Follow from the open questions

- [ ] Whatever is decided for questions 1 to 4 above: code, tests, spec updates and context updates.

### Before calling the project done

- [ ] Run `composer ci:check` in full and record the test count. The last count is 680, from Feature 14.
- [ ] Do a full live run on one region with real keys, from import to enrich to export, and check the results by hand.
