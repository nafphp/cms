<!-- managed-by: naf/cms agent-guide -->
# NafCMS Project Guide

This is an installed NAF host with NafCMS plugins. The host owns configuration, application code, public assets, and runtime data; Composer packages below `vendor/` are read-only dependencies. Put shared CMS behavior in a focused plugin or host module instead of editing `vendor/naf/cms`.

## Inspect First

Read these in order: `composer.json`, `app/config.php`, `app/plugins.php`, `app/routes.php`, `app/pages.php` plus `app/Resources/pages`, `app/api.php` plus `app/Resources/api`, `app/content.php` plus `app/Content`, `app/Migrations`, then the relevant `app/Handlers`, `app/Services`, `app/Repositories`, `app/Models`, `app/Mcp/Tools`, and `views`. Registration is explicit; do not assume a class is active because its file exists.

## Where Changes Belong

- Pages: define UI resources through `cms()->pages()` in `app/pages.php` or `app/Resources/pages/*.php`. Same-page form actions use a page data handler under `app/Handlers/Page` and must return a PSR response.
- APIs: define endpoints through `cms()->api()` in `app/api.php` or `app/Resources/api/*.php`; put invokable handlers under `app/Handlers/Api`.
- Content types: register through `cms()->contentTypes()` in `app/content.php`; keep type-specific code under `app/Content/<Type>`.
- Views: host templates live in `views`; plugin views are added to the NAF view path. Escape stored output and reuse NAF/NafCMS URL and sanitizing helpers.
- Domain behavior: workflows belong in narrowly named services; joins and optimized reads in repositories; entities and simple derived behavior in models.
- Schema: add explicit migrations under `app/Migrations` and register plugin migration paths during bootstrap.
- Extensions: plugin bootstrap may explicitly register pages, APIs, views, content/block types, commands, migrations, services, jobs, scheduler entries, navigation, permissions, and MCP tools. Never use hidden directory scans for feature activation.

## Commands

From the host root, use the CLI plugin's `vendor/bin/naf` executable:

```bash
composer install
vendor/bin/naf db:migrate up
vendor/bin/naf nafcms:assets:publish
vendor/bin/naf nafcms:assets:check
vendor/bin/naf user:create
vendor/bin/naf queue:consume
vendor/bin/naf schedule:ticker
vendor/bin/naf mcp:token:create
composer test
php -S 127.0.0.1:8080 -t public
```

Run `vendor/bin/naf` without a command to inspect the actual `queue:*`, `schedule:*`, and `mcp:*` commands if a host changes its optional package set. Docker is optional and only supported when this host deliberately ships a compose file; the bare-metal PHP/Composer path is the baseline.

## Safety and Generated Files

Auth is enabled by default. Apply permissions to admin pages/APIs and scopes to MCP tools; keep tokens, passwords, API secrets, `.env` data, logs, and runtime state out of Git. CMS actions exposed to AI must be concrete scoped tools, not raw filesystem or database access. Tool and plugin metadata are the capability source of truth; do not maintain a second AI capability list.

Do not edit `vendor/`, `node_modules/`, Composer-generated autoload files, published `.nafcms-assets.json`, compiled `public/css/nafcms.css`, or runtime files below `storage/nafcms` by hand. Edit their source/configuration and rerun the owning Composer, asset, migration, or CMS command. Before handing off, run the smallest relevant tests, then `composer test` and `vendor/bin/naf nafcms:assets:check` when assets changed.
