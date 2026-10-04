# Deployment Context

## Hosting

- Runs locally on Laravel Herd at `http://search_job.test` (or `php artisan serve`).
- No production host has been chosen. This is an open question in `progress-tracker.md`. Do not add deploy tooling until it is decided.

## Environment

- Local dev uses MySQL (`DB_CONNECTION=mysql`, database `laravel_search_job`).
- Local `APP_URL` is `http://search_job.test` (the Herd URL). Any other environment sets its own `APP_URL`.
- `.env.example` stays on SQLite because CI's `composer setup` copies it and migrates against it.
- Keep `.env.example` in sync whenever a new config key is added (for example the `sponsor-finder` keys).
- Quote any `.env` value that contains `#`, spaces, or `$`. An unquoted `#` starts a comment and silently empties the value.
- Secrets are never committed. See `security-context.md`.

## Running Locally

```bash
composer dev
```

This starts the server, queue worker, logs, and Vite together. The queue worker must be running for the enrich jobs to process.

The scheduler is needed for the hourly batch:

```bash
php artisan schedule:work
```

## Build

- `npm run build` compiles the frontend (Vite). Needed before running tests that render Inertia pages.
- For a production install: `composer install --no-dev --optimize-autoloader` and `npm run build`.

## Laravel Optimisations (production only)

- `php artisan optimize:clear` and then `php artisan optimize` after every deploy that changes config, routes, or views.
- `php artisan migrate --force` after backing up the database.

## Background Processing

- Queue driver is `database`. Without a running worker (`php artisan queue:work`), queued jobs do nothing.
- Without a scheduler (`schedule:work` locally, or a cron entry running `php artisan schedule:run` every minute on a server), the hourly batch never runs.
- After code changes, restart the worker (`php artisan queue:restart`) so it loads the new code.
