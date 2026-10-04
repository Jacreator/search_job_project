# Agent Rules

This is a Laravel 13 project built on the official Laravel React starter kit (Inertia + React + TypeScript, Fortify auth, teams). Check the installed versions in `composer.json` and `package.json` and follow the conventions for those versions. Do not assume older Laravel file layouts (for example `app/Console/Kernel.php` or `app/Http/Kernel.php`) — middleware, exceptions, and routing are configured in `bootstrap/app.php`, and the schedule lives in `routes/console.php`.

Starter kit code (auth, settings, teams, the app shell, and `resources/js/components/ui`) is the foundation. Build sponsor-finder features on top of it; do not rewrite it unless a feature spec says so.

## Application Building Context

Read the following files in order before implementing or making any architectural decision:

1. `context/project-overview.md` - product definition, goals, features, and scope
2. `context/architecture-context.md` - system structure, boundaries, storage model, and invariants
3. `context/data-model-context.md` - tables, migration and model conventions
4. `context/ui-context.md` - review dashboard look and component conventions
5. `context/code-standards.md` - implementation rules and conventions
6. `context/testing-context.md` - testing conventions and what must be covered
7. `context/security-context.md` - auth, team scoping, secrets, and input handling
8. `context/github-workflow-context.md` - branch, commit, PR, and CI conventions
9. `context/deployment-context.md` - environment, build, queue, and scheduler setup
10. `context/ai-workflow-rules.md` - development workflow, scoping rules, and delivery approach
11. `context/progress-tracker.md` - current phase, completed work, open questions, and next steps

Build one feature spec at a time from `context/feature-specs/`, in number order.

Update `context/progress-tracker.md` after each meaningful implementation change.

If implementation changes the architecture, scope, or standards documented in the context files, update the relevant file before continuing.

Ask the user before any major change (new dependency, schema redesign, removing starter kit features, or anything that contradicts a context file).
