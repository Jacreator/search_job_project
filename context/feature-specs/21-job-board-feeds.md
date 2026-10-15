# Job Board Feeds

**Status: draft, awaiting James's approval.**

When a sponsor's careers link points at Greenhouse, Lever, Ashby, or Workable, read the board's public JSON job feed instead of scraping the page. That gives real job titles and locations. Builds on specs 15, 16, and 20.

## Problem

Job boards build their pages with JavaScript, so a plain fetch often gets little or no job text. Developer roles and visa wording are then missed.

## Decisions For Approval

1. Four boards, each with a free public feed and no API key:

   | Board      | Careers link looks like                                            | Feed                                                                       |
   | ---------- | ------------------------------------------------------------------ | -------------------------------------------------------------------------- |
   | Greenhouse | `boards.greenhouse.io/{board}`, `job-boards.greenhouse.io/{board}` | `https://boards-api.greenhouse.io/v1/boards/{board}/jobs?content=true`     |
   | Lever      | `jobs.lever.co/{company}` (`jobs.eu.lever.co` in the EU)           | `https://api.lever.co/v0/postings/{company}?mode=json` (`api.eu.lever.co`) |
   | Ashby      | `jobs.ashbyhq.com/{org}`                                           | `https://api.ashbyhq.com/posting-api/job-board/{org}`                      |
   | Workable   | `apply.workable.com/{account}`                                     | `https://apply.workable.com/api/v1/widget/accounts/{account}`              |

   Each feed is checked against a live response before it is built, and that response is saved as a test fixture. A board whose feed does not work as expected is dropped from this spec and reported.

2. Job details are stored on the sponsor, not in a new table: a `careers_jobs` JSON column with up to 50 jobs, each `{title, location, url}`, plus `careers_source` (`page`, `greenhouse`, `lever`, `ashby`, `workable`).
3. With a feed, `mentions_developer_roles` comes from the job titles only (titles are precise, page text is noisy). `visa_sponsorship` comes from the job descriptions in the feed, where the feed has them, together with the careers page text.
4. All other boards (Workday, SuccessFactors, and so on) keep the spec 15 page check.

## Finding The Feed

- Look for a supported board URL among the homepage links (as today) and among the links on the careers page that was found. The first one found is used.
- The board name (`{board}`, `{company}`, `{org}`, `{account}`) is the first path segment. It must match `[A-Za-z0-9_-]+`, or the link is ignored.
- Feeds are fetched with `Http::timeout(10)->retry(2, ...)`, not through `PageFetcher`, because they are fixed, known hosts. They count against the `careers-pages` limiter as part of the same job.
- One `App\Contracts\JobBoardFeed` interface with one class per board, chosen by host. Each returns a list of `JobPosting` value objects (title, location, url, description text). A bad or empty response gives an empty list, never an error the job cannot handle. A 5xx or connection error counts as a fetch error (spec 20).

## Migration

`add_job_board_fields_to_sponsors_table`:

- `careers_source` (string, nullable, cast to `App\Enums\CareersSource`)
- `careers_jobs` (json, nullable): up to 50 jobs, developer roles first.

## Dashboard And Export

- Dashboard: the "Developer roles" cell shows the number of developer jobs when `careers_jobs` is set (for example "3 roles"), with the titles in a tooltip. The careers link opens the board.
- Export: a "Developer job titles" column (titles joined with `; `, developer roles only) and a "Careers source" column.
- Shared lookups copy `careers_source` and `careers_jobs`.

## Check When Done

- One fixture-based test per board: titles, locations, and URLs read; developer roles from titles; visa wording from descriptions where present.
- Board found on the homepage, and on the careers page.
- Bad board names ignored; empty and malformed feeds give no jobs; a 5xx counts as a fetch error.
- The 50-job cap, with developer roles kept first.
- Boards without a feed (Workday) still use the page check.
- Dashboard and export columns, and the shared lookup copy.
- `Http::preventStrayRequests()` in every test. No live request in tests.
- `security-context.md` lists the four feed hosts, and invariant 8 is updated to include them.
