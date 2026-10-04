# UI Context

The UI is one review dashboard inside the starter kit's logged-in app shell. Keep it plain and fast.

## Shell

- Use the existing `AppLayout` (sidebar + header) from `resources/js/layouts`.
- Add a "Sponsors" item to the sidebar nav (`resources/js/components/app-sidebar.tsx`), pointing at the current team's sponsors page.
- Breadcrumbs follow the pattern used by `resources/js/pages/dashboard.tsx`.

## Theme

- Use the starter kit's theme tokens (`resources/css/app.css`) and shadcn components. Light and dark mode both work, because the starter kit has an appearance setting.
- No raw hex colours. Use Tailwind classes, with `dark:` variants where a colour is set by hand.

## Components

Use what is already in `resources/js/components/ui` before adding anything:

| Need           | Component                      |
| -------------- | ------------------------------ |
| Card / panels  | `Card`                         |
| Status badge   | `Badge` with the classes below |
| Filters        | `Input`, `Select`, `Checkbox`, `Label` |
| Buttons        | `Button`                       |
| Feedback       | `sonner` toast                 |

If a new shadcn primitive is needed (for example `table`), install it with `npx shadcn@latest add <name>` and tell the user first.

## Status Badges

| Status  | Classes                                                       |
| ------- | ------------------------------------------------------------- |
| pending | `bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300`   |
| ch_done | `bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300`   |
| done    | `bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300` |
| skipped | `bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300` |
| failed  | `bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300`       |

## Second Check Badge

Rows with `needs_second_check` show a `Badge` reading "Needs second check" (`bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300`) next to the status.

## Confidence Colours

- 75 to 100: green text
- 50 to 74: amber text
- below 50: red text

## Layout

- Summary counts per status at the top (one small card per status).
- Filter row: status, town, minimum confidence, tech only, search by name.
- Paginated table: name, town, SIC codes, website (link), confidence, status, actions.
- Row action: edit website inline, mark confirmed.

## Rules

- One page component per route under `resources/js/pages/sponsors`. File names are kebab-case, like the rest of the starter kit.
- Filters live in the query string, so a filtered view can be bookmarked and survives reload.
- Forms use Inertia (`useForm` or `<Form>`) and Wayfinder route helpers, not hand-written URLs.
- Show validation errors with the existing `InputError` component.
- Links to websites open in a new tab with `rel="noopener noreferrer"`.
