# Sponsor Finder

## Overview

A Laravel app that takes the UK Home Office register of licensed sponsors and finds the employers where James can get a Software Engineer or AI Engineer job on a Skilled Worker visa. It finds which sponsors are tech employers, finds each one's official website and careers page, and checks whether they hire developers and whether they offer or refuse visa sponsorship. Sheffield and Yorkshire come first.

The app is built on the Laravel React starter kit. Users log in, belong to teams, and each team works on its own copy of the sponsor register.

## Goals

1. Import the sponsor register CSV into a team's sponsor list.
2. Put Sheffield and Yorkshire sponsors first, using a priority per town.
3. Skip B-rated sponsors by default.
4. Match each sponsor to its Companies House record.
5. Flag tech employers using SIC codes, name keywords, and a list of known employers.
6. Tag AI companies from their name or homepage.
7. Find the official website for each tech sponsor.
8. Give each website a confidence score so only doubtful rows need a human check.
9. Find each tech sponsor's careers page and check it for developer roles and whether it offers or refuses visa sponsorship.
10. Export the results to CSV.
11. Let team members review and fix low-confidence rows in a dashboard.

## Core Flow

1. User downloads the latest register CSV from GOV.UK.
2. User runs the import command for their team. Each row gets a region and priority from its town. B-rated rows are skipped.
3. The scheduler queues a batch of pending sponsors every hour, highest priority first.
4. A queued job looks up each sponsor on Companies House and saves its SIC codes and status.
5. The job classifies the employer (SIC code, name keyword, or known employer) and checks the name for AI terms.
6. Non-tech or inactive sponsors are marked as skipped, with a reason.
7. For tech sponsors, the job searches the web, scores candidate sites, and checks the homepage (including for AI terms).
8. The job saves the website and confidence score.
9. A second hourly job finds the careers page for done rows and records developer role and visa sponsorship signals.
10. User logs in and reviews rows in the team's dashboard.
11. User exports the team's final list.

## Features

### Import

- Read the register CSV row by row (low memory).
- Keep only worker routes: Skilled Worker, Global Business Mobility Senior or Specialist Worker, Global Business Mobility Graduate Trainee.
- Import into one team. Upsert by team, name, and town.
- Parse the A or B rating. Skip B-rated rows by default (configurable).
- Set region and priority from the town.

### Companies House Lookup

- Search by company name, accept only a confident name match.
- Fetch the company profile for SIC codes and status.

### Employer Classification

- A sponsor is tech when a SIC code is in the tech list, or its name has a tech keyword and no noise keyword, or it is on the known employers list.
- The reason is saved, so it is clear why a row counts as tech.
- Only active tech sponsors move on to the website step.

### AI Tagging

- Tag a sponsor as AI when its name or homepage mentions AI terms.
- The homepage check reuses the page already fetched for verification.

### Website Discovery

- Search the web through a search API.
- Ignore directory and social sites.
- Score each candidate by how well the domain and title match the company name.
- Load the best candidate homepage and raise the score if the company name appears.

### Careers Page And Hiring Signals

- Find the careers page from homepage links, known job board hosts, or common paths.
- Record whether the page mentions developer roles, and whether it offers or refuses visa sponsorship (refusal wording wins).
- Runs as its own job and rate limit, highest priority first. No paid APIs.

### Background Processing

- Queued jobs with rate limiting, retries, and backoff.
- Hourly scheduled batches until all rows are processed, highest priority first.
- Progress saved per row so a restart loses nothing.
- Teams can opt in to reuse each other's finished lookups, saving API calls.

### Export

- CSV of a team's done rows, filtered by minimum confidence, town, or region.
- Includes AI, careers page, developer roles, and visa sponsorship columns once those specs are built.

### Review Dashboard

- A "Sponsors" page inside the logged-in app, scoped to the current team.
- Table of processed sponsors with filters, including region, AI, developer roles, and visa sponsorship.
- Inline edit of the website, and a "confirmed" flag.
- Summary counts by status and skip counts by reason.

## Scope

### In Scope

- Login, registration, settings, and teams as provided by the starter kit (kept as is).
- Sponsor data owned by a team.
- Companies House and one web search provider.
- Location priority, rating filter, employer classification, and AI tagging.
- Careers page finder with developer role and visa sponsorship signals.
- CSV import and export via Artisan commands.
- Review dashboard behind login.

### Out Of Scope

- New roles or permissions beyond the starter kit's team roles.
- Scraping or storing individual job adverts. The careers check only records the careers page URL and yes or no signals.
- Emailing companies or applying for jobs.
- Paid data providers beyond the search API.
- Importing or exporting through the web UI (commands only for now).

## Success Criteria

1. The full register imports into a team in one command without running out of memory.
2. Every row ends in one status: done, skipped, or failed. Every skipped row has a skip reason.
3. A test batch of 50 Sheffield sponsors processes end to end.
4. Sheffield and Yorkshire rows are processed before lower priority rows.
5. At least 80 percent of done rows with confidence 75 or more have the correct website when spot checked.
6. Every done row with a website and confidence 50 or more has a careers check recorded.
7. The export opens cleanly in Excel and shows region, AI, careers page, developer roles, and visa sponsorship.
8. A team member can only see and edit their own team's sponsors.
