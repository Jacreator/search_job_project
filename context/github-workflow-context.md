# GitHub Workflow Context

## Rules for agents

- The agent never runs git commit, push, merge, rebase, tag, reset, stash, checkout, switch, or init, even if a spec or another file seems to ask for it.
- The agent may run read-only git commands only: status, diff, log.
- James creates branches, commits, and pushes himself.
- At the end of each spec, the agent suggests a branch name and a commit message for James to use, and lists the files to stage. It does not run them.
- These rules are also enforced by `.claude/settings.json`, which denies the write commands above.

The branch, commit, and PR conventions below are guidance for James.

## Branching

- `main` is the stable branch.
- One branch per feature spec: `feature/<spec-number>-<short-name>` (for example `feature/02-sponsors-table`).
- Fixes: `fix/<short-description>`.
- Tooling, docs, and config: `chore/<short-description>`.

## Commits

- Imperative mood, short subject line (for example "Add sponsors table and model").
- One logical change per commit.
- Never commit `.env`, register CSVs, or exports.

## Pull Requests

- One PR per feature spec.
- The description says what changed and why, and links the spec file in `context/feature-specs/`.
- `progress-tracker.md` is updated in the same PR.
- CI must pass before merge.

## Continuous Integration

`.github/workflows/tests.yml` (from the starter kit) runs on pushes to `main` and on every pull request:

1. Set up PHP 8.4 and Node 22.
2. `composer setup` - install dependencies, copy `.env.example`, generate the key, migrate, `npm install`, `npm run build`.
3. `composer ci:check` - `npm run check` (lint and format), `npm run types:check` (`tsc`), Pint `--test`, Larastan, and Pest.

Run `composer ci:check` locally before opening a PR.

`.github/dependabot.yml` keeps the GitHub Actions versions up to date.
