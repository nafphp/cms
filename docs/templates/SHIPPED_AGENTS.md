# NixCMS Project Guide

This file is intended for people and AI agents working inside an installed NixCMS project.

## Structure

- `app/config.php`: application and plugin configuration.
- `app/routes.php`: HTTP routes.
- `app/Controllers`: web, admin, and API/action controllers.
- `app/Services`: reusable domain workflows.
- `app/Repositories`: persistence queries.
- `app/Models`: ORM entities and entity behavior.
- `app/Migrations`: database migrations.
- `app/Mcp/Tools`: MCP tools exposed to AI clients.
- `views`: server-rendered `.phtml` templates.
- `views/components`: self-contained view components; prefer overriding these before editing global CSS.
- `app/Resources`: source assets, language files, and prompts.
- `public`: webroot and built public assets.
- `storage`: runtime files.
- `logs`: application logs.

## Local Run

Install dependencies with Composer, then run the PHP built-in server:

```bash
composer install
composer serve
```

The web server DocumentRoot must be `public`. Unknown paths should be routed
through the front controller (`public/index.php`); static files that exist under
`public/css`, `public/js`, fonts, and images may be served directly.

Use the project CLI wrapper from the project root:

```bash
bin/nix command:name
```

## Conventions

- Keep controllers thin.
- Put reusable workflows in services.
- Put query logic in repositories.
- Keep MCP tools concrete and scope-aware.
- Do not edit generated build output manually.
- Browser-consumed CSS and JS live under `public/css` and `public/js`; source/build inputs can live under `app/Resources`.
- Admin component styling should live in the component markup whenever practical. Keep global CSS for tokens, base rules, and infrastructure only.
- Keep secrets out of version control.
