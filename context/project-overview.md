# Sponsor Finder

## Overview

A Laravel app that takes the UK Home Office register of licensed sponsors, finds which sponsors are tech companies, and finds each one's official website. The aim is a clean list of UK tech companies that can sponsor a Skilled Worker visa, so the user can apply to them directly.

The app is built on the Laravel React starter kit. Users log in, belong to teams, and each team works on its own copy of the sponsor register.

## Goals

1. Import the sponsor register CSV into a team's sponsor list.
2. Match each sponsor to its Companies House record.
3. Flag tech companies using their SIC codes.
4. Find the official website for each tech sponsor.
5. Give each website a confidence score so only doubtful rows need a human check.
6. Export the results to CSV.
7. Let team members review and fix low-confidence rows in a dashboard.

## Core Flow

1. User downloads the latest register CSV from GOV.UK.
2. User runs the import command for their team.
3. The scheduler queues a batch of pending sponsors every hour.
4. A queued job looks up each sponsor on Companies House and saves its SIC codes and status.
5. Non-tech or inactive sponsors are marked as skipped.
6. For tech sponsors, the job searches the web, scores candidate sites, and checks the homepage.
7. The job saves the website and confidence score.
8. User logs in and reviews low-confidence rows in the team's dashboard.
9. User exports the team's final list.

## Features

### Import

- Read the register CSV row by row (low memory).
- Keep only worker routes: Skilled Worker, Global Business Mobility Senior or Specialist Worker, Global Business Mobility Graduate Trainee.
- Import into one team. Upsert by team, name, and town.

### Companies House Lookup

- Search by company name, accept only a confident name match.
- Fetch the company profile for SIC codes and status.

### Tech Classification

- A sponsor is tech when any SIC code is in the agreed tech list.
- Only active tech sponsors move on to the website step.

### Website Discovery

- Search the web through a search API.
- Ignore directory and social sites.
- Score each candidate by how well the domain and title match the company name.
- Load the best candidate homepage and raise the score if the company name appears.

### Background Processing

- Queued jobs with rate limiting, retries, and backoff.
- Hourly scheduled batches until all rows are processed.
- Progress saved per row so a restart loses nothing.
- Teams can opt in to reuse each other's finished lookups, saving API calls.

### Export

- CSV of a team's done rows, filtered by minimum confidence, town, or region.

### Review Dashboard

- A "Sponsors" page inside the logged-in app, scoped to the current team.
- Table of processed sponsors with filters.
- Inline edit of the website, and a "confirmed" flag.
- Summary counts by status.

## Scope

### In Scope

- Login, registration, settings, and teams as provided by the starter kit (kept as is).
- Sponsor data owned by a team.
- Companies House and one web search provider.
- CSV import and export via Artisan commands.
- Review dashboard behind login.

### Out Of Scope

- New roles or permissions beyond the starter kit's team roles.
- Scraping job adverts.
- Emailing companies or applying for jobs.
- Paid data providers beyond the search API.
- Importing or exporting through the web UI (commands only for now).

## Success Criteria

1. The full register imports into a team in one command without running out of memory.
2. Every row ends in one status: done, skipped, or failed.
3. A test batch of 50 Sheffield sponsors processes end to end.
4. At least 80 percent of done rows with confidence 75 or more have the correct website when spot checked.
5. The export opens cleanly in Excel.
6. A team member can only see and edit their own team's sponsors.
