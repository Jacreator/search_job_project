# Company Page

**Status: draft, awaiting James's approval.**

A public page for one company at `/companies/{public id}`, linked from each row of the directory. Same owner team, listing, and public safe field rules as spec 24. Builds on specs 23 and 24.

## Route

- `GET /companies/{company}` (`companies.show`), `{company}` constrained by `whereUlid`, in the throttled public group from spec 24.
- `CompanyController::show` resolves the row through `PublicDirectory`: the public team's sponsors, `publiclyListed()`, `public_id` equal to the parameter. Anything else returns 404: no public team, a row of another team, a row that is not listed, or an unknown id. The 404 never says which.

## What The Page Shows

Built by a separate `App\Http\Resources\PublicCompanyDetailResource`, with explicit keys, following the spec 24 rules:

| Item                    | Rule                                                                                                     |
| ----------------------- | -------------------------------------------------------------------------------------------------------- |
| Name, town, region      | As stored                                                                                                |
| Sector                  | Section letter and name (spec 23)                                                                        |
| SIC codes               | Each code with its description (see SIC Descriptions). An unknown code shows "Description not available" |
| Companies House number  | With a link to `https://find-and-update.company-information.service.gov.uk/company/{number}` (new tab)   |
| Company status          | From Companies House, shown as words ("Active")                                                          |
| Register routes, rating | The routes as a list, and the rating grade                                                               |
| Website                 | Only when confirmed or confidence 75 or more (spec 24)                                                   |
| Careers page            | Only when the website is shown                                                                           |
| Developer roles         | Yes, No, or "Not checked", only when the website is shown                                                |
| Visa sponsorship        | "Offered", "Not offered", or "No information", only when the website is shown                            |
| AI tag                  | As in spec 24                                                                                            |
| Last checked            | See Open Point below                                                                                     |

Never shown: everything spec 24 lists as never sent, except the Companies House number, which is public data and shown here.

## SIC Descriptions

- Source: the Companies House "SIC 2007 condensed list" CSV on GOV.UK, from the "Standard industrial classification of economic activities (SIC)" publication. It is the list Companies House itself uses, published under the Open Government Licence v3.0.
- Stored as `config/sic-codes.php`: an array of 5-digit code to description, generated once from that CSV and committed. A comment at the top gives the source URL, the download date, and the licence. A config file is cached by `php artisan optimize` with the rest of the config, so lookups are one array read.
- `App\Support\SicCodes::describe(string $code): ?string`, a pure helper reading that config.
- The CSV is downloaded once by hand while building this spec. The app never downloads it at run time (invariant 1).

## Open Point: Last Checked

There is no column that records when the lookup ran. `updated_at` changes on every re-import and dashboard edit, so it would be misleading. Two options, for James to choose:

1. **Add `looked_up_at`** (timestamp, nullable), set by `EnrichSponsor` when a row reaches `done` or `skipped`, copied by shared lookups, and filled for existing rows from `updated_at` once. The page shows "Company details checked {date}" and, when set, "Careers page checked {date}" from `careers_checked_at`. A schema change beyond specs 23 and 24, so it needs approval.
2. **No new column**: show only "Careers page checked {date}" from `careers_checked_at`, when it is set and the website is shown.

Recommended: option 1.

## Page

- `resources/js/pages/companies/show.tsx`, in the spec 24 public layout.
- A heading with the name, then a definition list of the items above, then the links. A "Back to the directory" link.
- Page title: the company name. External links open in a new tab with `rel="noopener noreferrer"`.

## Check When Done

- A listed row of the public team shows every item above, with the right Companies House link and SIC descriptions.
- 404 for: no public team, a row of another team, a pending, failed, `NoMatch`, `Inactive`, or `BRating` row, an unknown ULID, and the numeric id in the URL.
- The props have exactly the public detail keys and none of the internal ones, including in the shared props.
- Website, careers page, developer roles, visa, and homepage AI tag hidden together when the website is not trusted (confidence 74, unconfirmed).
- An unknown SIC code shows "Description not available".
- Config test: `config/sic-codes.php` has the expected number of codes from the CSV, and sample codes (`62012`, `86101`) have the right descriptions.
- The throttle applies.
- Each directory row links to its page.
- Last checked works as chosen in the open point.
- `architecture-context.md`, `ui-context.md`, `security-context.md`, and `data-model-context.md` (if option 1) updated.
