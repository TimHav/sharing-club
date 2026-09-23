# AGENTS.md

## Project
This repository contains the Sharing Club WordPress plugin. It is a custom post type plugin for lending and borrowing shared items such as books, tools, and toys.

## Working style
- Keep changes small and targeted.
- Prefer the existing code style and naming patterns already used in the plugin.
- Preserve WordPress compatibility and avoid introducing unnecessary dependencies.
- When editing PHP, keep functions, hooks, and templates aligned with the current plugin conventions.

## Relevant files
- `readme.txt`: plugin metadata and user-facing documentation.
- `sharing-club.php`: main plugin bootstrap and registration logic.
- `admin-*.php`: admin screens and list/table behavior.
- `templates/*.php`: frontend display templates for shared items.
- `css/` and `js/`: styling and client-side behavior.

## Development Notes
- This is a WordPress plugin, so follow plugin architecture and hooks rather than introducing framework patterns.
- Preserve translation support and existing i18n strings.
- Prefer compatibility with older WordPress versions when making changes.
- When asked for a fix or feature, validate the behavior with the smallest possible check and keep the patch focused.

## Answer style
- Be concise.
- Prefer direct answers over long explanations.
- Summarize the result in a few lines when possible.
- If a task is simple, do not add extra commentary.
- If more detail is needed, keep it specific and relevant.
