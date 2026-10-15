# Status Summary and Second-Pass Backlog

All 15 feature specs are built. The second pass handles everything listed below. Add new items here as they come up, instead of fixing them on the spot. `progress-tracker.md` keeps the full history.

## Where Things Stand (2026-10-14)

- Done: all 15 features (setup, sponsors table, CSV import, Companies House, employer classification, web search, website scoring, enrich job, batch and schedule, export, sponsor edit permission, review dashboard, shared lookups, AI tagging, careers page and hiring signals).
- Next: the second pass below. Specs 16 to 22 (drafts, awaiting James's approval) cover the fixes and additions James listed on 2026-10-14.

## Open Questions

Decide these before the second pass starts.

1. **Clearing and editing websites on the dashboard.** Should an editor be able to clear a wrong website, not only replace it? Should they be able to edit rows that are not `done`, for example a row skipped as not tech? Both are refused for now.
2. **Leading zeros in the export.** Excel strips the leading zeros of company numbers (for example `03917515`) when it opens the CSV. Should the export protect them, and how? A `="03917515"` cell works in Excel, but other tools show it as written.
3. **Search provider.** Which provider to use long term if Brave's limits are too tight.
4. **Hosting.** Where the app will run, if anywhere beyond local Herd. This decides what `deployment-context.md` has to cover (queue worker, scheduler, environment).

## Draft Specs Awaiting Approval

| Spec                      | What it does                                                                                                                                      |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| 16 URL resolution         | One `Url::resolve()` for redirects and links: relative paths, query-only, `./` and `../`                                                          |
| 17 Stronger website check | Full name on the page +25, first two words +10, no first-word-only bonus                                                                          |
| 18 Retry failed rows      | `sponsors:retry --failed` puts failed rows back to `pending` or `ch_done`                                                                         |
| 19 AI name rule           | Name tags only on tech rows; `sponsors:retag-ai` re-applies the rule                                                                              |
| 20 Careers re-checks      | Re-check after 30 days; fetch errors retried later, not "nothing found"                                                                           |
| 21 Job board feeds        | Greenhouse, Lever, Ashby, Workable JSON feeds for real job titles                                                                                 |
| 22 Shared companies       | Proposal only: one `companies` table shared by all teams, if the app goes multi-user                                                              |
| 23 Sector labels          | SIC 2007 section (A to U) for every sponsor with SIC codes; dashboard shows the sector instead of "Not tech"; fill command, filter, export column |
| 24 Public directory       | Public `/companies` for one owner team (`SPONSOR_PUBLIC_TEAM`), public safe fields only, ULID `public_id`, throttled, no export                   |
| 25 Company page           | Public `/companies/{public id}` with SIC descriptions (from the Companies House condensed list) and a Companies House link                        |

## Open Questions For Specs 23 To 25

1. **Wording for "not tech" (spec 23).** Rows skipped as not tech show their sector. The summary card for that skip reason becomes "Other sectors". Is that the wording you want?
2. **Who is listed publicly (spec 24).** Proposed: `done` rows and rows skipped as not tech (matched on Companies House and active). Pending, failed, no match, inactive, and B-rated rows are never listed. Should rows with no Companies House match be listed too, with register data only?
3. **Hiding careers data with the website (spec 24).** Proposed: careers page, developer roles, visa signal, and homepage AI tags are only shown when the website is shown (confirmed or confidence 75 or more), because they come from that website. OK?
4. **Last checked (spec 25).** No column records when the lookup ran. Add a `looked_up_at` timestamp (recommended, a schema change beyond the brief), or show only the careers check date?
5. **Search engines.** Should the public pages be indexed by search engines, or carry `noindex` until you decide?
6. **Rate limit.** 60 requests per minute per IP for the public pages (`SPONSOR_PUBLIC_RATE_PER_MINUTE`, a second new env key). OK?
7. **Spec 22.** If the shared companies table (spec 22) goes ahead, sector and the public fields belong on `companies`. Build 23 to 25 first on `sponsors`, or decide 22 first?
8. **Spec 17.** The stronger website check would lower some confidences, so fewer websites would reach the public 75 threshold. Build 17 before 24?

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

- [ ] False positives from "Ai" as a person's name or initials, and rows looked up before Feature 14 with no AI tag: covered by spec 19.
- [ ] Live run to see homepage tagging on real sites.

### Careers pages (Feature 15)

- [ ] Re-checks after 30 days and retrying fetch errors later: covered by spec 20.
- [ ] Careers pages built with JavaScript: covered for Greenhouse, Lever, Ashby and Workable by spec 21. Workday and SuccessFactors still use the page check.
- [ ] Larger live run: only 7 local rows were eligible (5 pages found).

### Revisit if things change

- [ ] Priority locations are one global config list. Make them per team only if a team outside Yorkshire uses the app.

### Follow from the open questions

- [ ] Whatever is decided for questions 1 to 4 above: code, tests, spec updates and context updates.

### Before calling the project done

- [ ] Run `composer ci:check` in full and record the test count. The last count is 753, from Feature 15.
- [ ] Do a full live run on one region with real keys, from import to enrich to export, and check the results by hand.
