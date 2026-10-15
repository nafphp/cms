# NafCMS

NafCMS is a lightweight, extensible CMS built on top of NAF. It is bare-metal first: PHP 8.3+, Composer, server-rendered `.phtml` templates, Tailwind v4, and optional MCP/AI integration without a separate documentation-site toolchain.

> **This repository is a NAF plugin, not an application.** It ships no front
> controller and cannot be served or booted on its own. You install it into a
> host application, which owns `BASE_PATH`, the configuration, the webroot and
> the request lifecycle — see [Quickstart](#quickstart). Your own code belongs
> in that host, not in this package.

PHP 8.3 and 8.4 are the supported release targets and are required CI gates. Development and verification happen on PHP 8.3, the version the reference container runs. The package requires `>=8.3` without an upper bound, so newer PHP versions install; they are exercised by a compatibility job rather than a release-blocking gate.

## Quickstart

Prerequisites are PHP 8.3 or 8.4 with cURL, DOM, PDO, and either PDO SQLite or PDO MySQL, plus Composer 2. Node.js is needed only when changing NafCMS frontend sources, not in an installed host.

The package requires Framework 0.2.9 or newer for rendering from commands and workers, and CLI 0.2.2 or newer for Composer-installed command bootstrap paths. PDO belongs to the CMS's own requirements.

Create a regular NAF host application, then install NafCMS as a plugin:

```bash
composer create-project naf/app my-cms
cd my-cms
composer require naf/cms
```

NAF discovers `naf/cms` from its Composer package type and loads its configuration, routes, views, migrations, commands, and MCP tools. Because those registrations consume the other NAF plugins, list NafCMS last in the host's `app/plugins.php`:

```php
return [
    'naf/cli',
    'naf/database',
    'naf/session',
    'naf/form',
    'naf/view',
    'naf/i18n',
    'naf/orm',
    'naf/queue',
    'naf/schedule',
    'naf/mail',
    'naf/mcp',
    'naf/cms',
];
```

This uses NAF's normal explicit plugin-order mechanism; it does not copy NafCMS source into the host. The host owns `BASE_PATH`, its configuration, runtime storage, public webroot, and application lifecycle; NafCMS never starts the host from its plugin bootstrap. If the ordering file is missing, the bootstrap reports which dependency was not loaded yet instead of failing later with an undefined helper or registry.

For a small SQLite installation, configure the host before running migrations:

```php
// app/config.php
return [
    'database' => [
        'driver' => 'sqlite',
        'database' => BASE_PATH . '/storage/nafcms.sqlite',
    ],
];
```

For MariaDB/MySQL, set the host's `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE` values instead. `install:database` may create a configured database and migrate it; `db:migrate up` only applies pending migrations and is the safer deployment command when infrastructure owns database creation.

After the plugin order and database are configured, finish setup:

```bash
vendor/bin/naf nafcms:agents:install
vendor/bin/naf nafcms:assets:publish
vendor/bin/naf db:migrate up
vendor/bin/naf user:create admin admin@example.com admin
vendor/bin/naf mcp:token:create
php -S 127.0.0.1:8080 -t public
```

`nafcms:agents:install` creates the short project guide used by people and AI coding agents at the host root. It never overwrites an existing user-owned `AGENTS.md`; `--update` refreshes only a file carrying the NafCMS managed marker. The current starter already ships a guide, so this command reports that it was preserved and exits with status 1. Review and combine the [CMS guide template](docs/templates/SHIPPED_AGENTS.md) with the host's instructions when needed. The guide gives a synchronized inspection order and the explicit Page, API, content, migration, view, handler, service, repository, model, plugin, and MCP extension points.

`user:create` requests the password twice with terminal echo disabled. For non-interactive installation, pass `--password-file` with a short-lived mode-`0600` file instead of exposing a password in shell history or the process list. Open `http://127.0.0.1:8080/admin` after installation. Configure the host database in `app/config.php` or its environment file before migrating. Production web servers should use the host's `public` directory as the DocumentRoot and rewrite missing files to `index.php`.

In production, terminate TLS, keep auth enabled, restrict writable paths to host runtime/public asset targets, and keep `.env`, database credentials, MCP tokens, logs, and `storage/nafcms` outside the public webroot. Run `nafcms:assets:check` after Composer deployment, back up the database and installation-owned runtime files, and supervise `queue:consume` plus `schedule:ticker` only when those workflows are used. The PHP built-in server is for local development, not production.

For development of NafCMS itself, work through a host application — this package cannot be served on its own. [NAF Studio](https://github.com/nafphp/studio) is the reference environment: it ships a Docker setup and mounts this repository as the `naf/cms` dependency, so edits take effect immediately and every CLI command runs from the host:

```bash
composer install                       # in this repository, for the test suite
vendor/bin/phpunit                     # unit tests, no host required

# everything else runs in the host, e.g. NAF Studio:
vendor/bin/naf db:migrate up
vendor/bin/naf user:create admin admin@example.com admin
```

The asset publisher copies only the package's `css`, `js`, `fonts`, and `images` trees into the configured host public directory. `.nafcms-assets.json` records ownership and hashes: repeat publishes update unchanged owned files, while host-modified collisions stop with an error. Use `vendor/bin/naf nafcms:assets:check` in deployments and `vendor/bin/naf nafcms:assets:remove` before uninstalling; removal never deletes a modified host file.

When developing NafCMS itself, frontend assets are intentionally simple:

```bash
npm install
npm run build
npm run test:assets
```

Use `npm run dev` while working on Tailwind or admin UI assets.

## Release Gates

Every change is checked on PHP 8.3 and 8.4 with Composer validation, PHPUnit, generated-documentation drift detection, and the repository integrity check. PHP 8.3 also tests the lowest allowed NAF dependencies. Separate jobs rebuild the Tailwind asset, audit locked Composer and npm dependencies, apply all migrations to MariaDB, inspect the Composer archive, and install the mirrored package into a freshly generated NAF host. The host smoke test verifies plugin boot idempotence, views and admin routing, commands, SQLite migrations, published assets, authenticated admin boundaries, scoped MCP execution, and that normal host writes never modify `vendor/naf/cms`.

Run the same package-level checks locally with:

```bash
scripts/check-package.sh
scripts/package-smoke.sh
```

The smoke script needs network access to create the temporary official `naf/app` host. It checks preservation of the starter's guide and installation of a CMS-managed guide, deletes the fixture after the run, and does not require Docker; MariaDB is only an additional CI migration gate. Static analysis scans the dependency helpers without booting the application or reading a local environment file. Tailwind builds scan only the declared package PHP, template and JavaScript sources, so temporary hosts and local tooling do not affect generated CSS.

## Sitebuilder Preview

The v0.0.1 Sitebuilder is an explicitly experimental preview. The supported built-in templates are Default, Landing page, Editorial index, Two column, Feature grid, Article detail, Full bleed, and Custom layout. The supported block registry includes the shipped text, heading, hero, media/image, embed/map, navigation/header/footer, CTA, feature, quote, form, articles/article, chart/stats, and nested section/stack/columns/grid block types. Plugin block types participate only when they provide the same field metadata contract.

Builder requests carry `builderVersion: 1`. The server derives validation from each registered block type's fields: text and textarea lengths, numeric ranges, select options, booleans, media/link/embed URL policy, statistics, chart metrics, and nested children. Unknown slot IDs, block types, payload fields, and block properties are rejected with validation errors instead of being silently persisted. Payloads are limited to 256 KiB, 50 custom slots, 100 total blocks, 24 children per container, and four nested container levels. The normalized data is applied both when saving and when `PageFactory` constructs a preview or public page.

The preview does not promise payload migration or backward compatibility before v0.1. It has no undo/redo, collaborative editing, or guaranteed autosave recovery; responsive controls are viewport approximations rather than per-breakpoint authoring. Deep nested layouts remain deliberately constrained, and browser-local draft state is not a substitute for a successful server save. Run the representative DOM flow with:

```bash
npm run test:builder
```

That flow adds and configures a block, creates a nested layout, saves and reloads it, checks preview and published payload state, and confirms that a rejected save leaves the editable data intact.

## Runtime State and Package Updates

Package defaults remain read-only under `vendor/naf/cms`. Installation-specific prompts, AI memory, mistake logs, translation overrides, rate-limit counters, and embedding caches live below the host-relative `storage/nafcms` directory by default. Override the root in the host's `app/config.php` when needed:

```php
return [
    'cms' => [
        'runtime_path' => 'storage/nafcms',
    ],
];
```

Relative runtime paths are resolved below the host `BASE_PATH` and may not contain `..`; absolute paths are allowed for deliberate external volumes. Reads use host override first and immutable package default second, so prompt and translation customizations survive Composer updates. Runtime files are written atomically with restrictive permissions. Generated theme CSS is written atomically below the host's configured `view.assets.publicPath`; arbitrary theme JavaScript remains disabled.

The v0.0.1 local Ollama assistant has no persisted API-key field. A future remote provider must introduce a real secret store before accepting credentials. Global prompt/memory/mistake administration requires `ai.instructions.manage`, and editable theme code requires `themes.code.manage`.

Normal AI tool-routing fallbacks record a SHA-256 query fingerprint, character count, requested mode/capabilities, and UI area—not the user text. A host may deliberately set `ai.routing_debug=true` while diagnosing routing; debug text is capped at 500 characters and should not be enabled in routine production operation. MCP tools may implement `Naf\CMS\Contracts\LocalAiToolMetadataInterface` to provide their title, routing text, examples, capabilities, mode, risk, and auto-run policy. These tool-owned values take precedence; the catalog only derives missing metadata.

AI and MCP features are permission-, scope-, and tool-driven. The executable tool registry and plugin metadata define what an assistant can do; NafCMS does not promise or maintain a separate static capability catalog. Browser-local Ollama support selects a small relevant tool set, concrete write tools use the same CMS domain APIs and permissions as other callers, and MCP Bearer tokens should receive only the scopes their client needs.

The local assistant's confirmation dialog is user guidance, not a security
boundary. Tool-declared `risk` and `destructive` metadata decide when it is
shown; server-side API permissions and the MCP scopes derived by
`LocalAiToolBridge` remain authoritative even if a client omits that dialog.

Removing the Composer package intentionally leaves `storage/nafcms` and generated theme CSS in place because they are installation data. Back up or remove those paths explicitly after running `nafcms:assets:remove` if the installation is being permanently deleted.

## Outbound HTTP Security

Server-side form webhooks accept public HTTPS targets only. Before connecting, NafCMS resolves and validates every target address, pins cURL to a validated address, disables proxy inheritance, and repeats the policy check for every redirect. Loopback, private, link-local, multicast, documentation/reserved, and cloud-metadata networks are blocked by default.

Local development HTTP webhooks require both `cms.outbound_http.webhooks.allow_development_http=true` and an exact hostname in `cms.outbound_http.webhooks.development_hosts`; do not enable this exception in production. Server-side Ollama embeddings are intentionally narrower: `ai.url` must use `localhost`, `127.0.0.1`, or `::1` and a port listed in `cms.outbound_http.ollama.allowed_ports` (`11434` by default). If the embedding target is blocked or unavailable, tool selection falls back to the deterministic keyword router.

## Authentication and HTTP Defaults

Authentication is always enabled in the v0.0.1 preview; there is no implicit setup or auth-off mode. Failed logins are temporarily limited by hashed username/IP and IP-only buckets, and public form submissions have per-form/IP rate limits, a 64 KiB body limit, per-field limits, a honeypot, and a minimum-fill-time check. Limiter files contain only counters, reset times, and hashed keys.

Logout is a CSRF-protected `POST`. User creation, invites, admin password resets, and the bootstrap CLI share the same password policy. The CLI requests passwords with terminal echo disabled or accepts a protected `--password-file`; passwords must never be passed as positional shell arguments.

NafCMS adds CSP, clickjacking, MIME-sniffing, referrer, browser-permission, and HTTPS HSTS headers centrally. The preview CSP still permits inline scripts/styles because the current server-rendered admin and editor use them, but executable theme JavaScript remains disabled. Session cookies come from `naf/session` with `HttpOnly`, `SameSite=Lax`, and `Secure` when HTTPS is detected. Behind TLS-terminating proxies, set `session.trust_proxy_headers=true` only together with exact `session.trusted_proxies` addresses; never trust forwarded protocol headers from arbitrary clients.

## First Page

Register pages through the CMS registry. A plugin can run this from its bootstrap file:

```php
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;
use function Naf\View\view;

cms()->pages()->add(
    Page::create('plugin.reports')
        ->setPath('/admin/reports')
        ->setTitle('Reports')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_VIEW)
        ->setAdminNavigation('Reports', parent: 'content', icon: 'stats', key: 'plugin.reports')
        ->setContent(fn () => view('admin.reports.index'))
);
```

## Admin Navigation

`Scope::FRONTEND` is the default CMS scope. Use `cms()->scope()`, `cms()->scope(Scope::FRONTEND)`, or the direct unscoped helpers for frontend-facing registrations, and switch to `Scope::ADMIN` only when extending the admin area.
Scoped CMS APIs are generic: any CMS entry point that returns an object implementing `Naf\CMS\Contracts\ScopeAwareInterface` is automatically bound when called through `cms()->scope(...)`.

Use the page metadata when the menu item belongs to a registered admin page:

```php
Page::create('plugin.reports')
    ->setPath('/admin/reports')
    ->setAdminNavigation('Reports', parent: 'content', icon: 'stats', key: 'plugin.reports');
```

Use `cms()->scope(Scope::ADMIN)->navigation()->add(...)` when a plugin contributes admin navigation metadata directly, for example a parent item, an external tool link, or a menu entry registered outside a page definition:

```php
use Naf\CMS\Enums\Scope;
use Naf\CMS\Navigation\NavigationItem;

cms()->scope(Scope::ADMIN)->navigation()->add(
    NavigationItem::create('plugin.reports')
        ->setLabel('Reports')
        ->setHref('/admin/reports')
        ->setParent('content')
        ->setIcon('stats')
);
```

## How Do I Build...

- A permission-protected admin page: [examples/01-page-with-permission](examples/01-page-with-permission/)
- A custom content type: [examples/02-custom-content-type](examples/02-custom-content-type/)
- A page-builder block type: [examples/03-block-type](examples/03-block-type/)
- An MCP tool: [examples/04-mcp-tool](examples/04-mcp-tool/)

Run `composer test` after changing examples; the smoke test loads them against the CMS registries so stale docs fail like stale code.

## Services überschreiben

NafCMS bindet austauschbares Verhalten an kleine Interfaces. Ein Host lädt
`app/src/services.php` am Anfang seiner `app/src/routes.php`; dieser Schritt
läuft nach den Plugin-Bootstraps, sodass die Host-Bindings die Defaults
ersetzen. Closures halten die Auflösung lazy:

```php
use Naf\CMS\Contracts\MediaStorageInterface;
use App\Media\S3MediaStorage;
use function Naf\app;

app()->container()->set(
    MediaStorageInterface::class,
    static fn(): MediaStorageInterface => new S3MediaStorage(),
);
```

Die vollständige Struktur steht in
[examples/06-service-override](examples/06-service-override/).

## Auto Reference

These sections are generated from registries and enums. Edit the source code, then run `vendor/bin/naf docs:sync` from a host that has this package installed.

### Public `cms()` Entry Points

<!-- naf:auto:cms -->
<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->

| Entry point | Purpose |
| --- | --- |
| `cms()->pages()` | Renderable HTTP pages and page data handlers. |
| `cms()->scope(Scope::ADMIN)` | Scope-bound CMS APIs; defaults to Scope::FRONTEND. |
| `cms()->api()` | JSON/API/action endpoints. |
| `cms()->contentTypes()` | Registered CMS content types. |
| `cms()->content(Model::class)` | Resolve a content API by model class. |
| `cms()->articles()` | Convenience API for the Article content type. |
| `cms()->forms()` | Convenience API for the Form content type. |
| `cms()->media()` | Convenience API for the Media content type. |
| `cms()->navigation()` | Site navigation content and navigation metadata registration. |
| `cms()->storage(Model::class)` | Criteria-based CRUD for one ORM entity. |
| `cms()->visualizations()` | Registered chart definitions and rendering. |
| `cms()->blocks()` | Page-builder block type to view mappings. |
| `cms()->blockTypes()` | Page-builder block metadata for the admin builder. |
| `cms()->themes()` | Frontend theme assets and builder-visible surfaces. |
| `cms()->languages()` | Enabled, available, and discovered language codes. |
| `cms()->settings()` | Resolved runtime settings and admin-editable setting metadata. |
<!-- /naf:auto:cms -->

### Pages

<!-- naf:auto:pages -->
<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->

| ID | Path | Layout | Permissions | Data handler |
| --- | --- | --- | --- | --- |
| `home` | `/` | - | - | no |
| `admin.dashboard` | `/admin` | `layouts.admin` | `admin.access` | no |
| `admin.articles` | `/admin/articles` | `layouts.admin` | `articles.view` | no |
| `admin.articles.create` | `/admin/articles/create` | `layouts.admin` | `articles.create` | yes |
| `admin.articles.version.create` | `/admin/articles/{articleId}/version/create` | `layouts.admin` | `articles.create` | yes |
| `admin.articles.translation.create` | `/admin/articles/{articleId}/{language}/create` | `layouts.admin` | `articles.create` | yes |
| `admin.articles.edit` | `/admin/articles/{articleId}/{language}/edit` | `layouts.admin` | `articles.edit` | yes |
| `admin.articles.preview` | `/admin/articles/{articleId}/{language}/preview` | `layouts.admin` | `articles.edit` | no |
| `admin.articles.version.edit` | `/admin/articles/{articleId}/{language}/{version}/edit` | `layouts.admin` | `articles.edit` | yes |
| `admin.forms` | `/admin/forms` | `layouts.admin` | `forms.view` | no |
| `admin.forms.create` | `/admin/forms/create` | `layouts.admin` | `forms.create` | yes |
| `admin.forms.edit` | `/admin/forms/{formId}/edit` | `layouts.admin` | `forms.edit` | yes |
| `admin.forms.submissions` | `/admin/forms/{formId}/submissions` | `layouts.admin` | `forms.submissions.view` | no |
| `auth.login` | `/admin/login` | - | - | yes |
| `auth.logout` | `/admin/logout` | - | - | no |
| `admin.media` | `/admin/media` | `layouts.admin` | `media.view` | no |
| `admin.navigation` | `/admin/navigation` | `layouts.admin` | `navigation.view` | no |
| `admin.navigation.create` | `/admin/navigation/create` | `layouts.admin` | `navigation.create` | yes |
| `admin.navigation.edit` | `/admin/navigation/{navigationId}/edit` | `layouts.admin` | `navigation.edit` | yes |
| `admin.pages` | `/admin/pages` | `layouts.admin` | `pages.view` | yes |
| `admin.pages.create` | `/admin/pages/create` | `layouts.admin` | `pages.create` | no |
| `admin.roles` | `/admin/roles` | `layouts.admin` | `roles.manage` | no |
| `admin.settings` | `/admin/settings` | `layouts.admin` | `settings.manage` | no |
| `admin.settings.ai` | `/admin/settings/ai` | `layouts.admin` | `settings.manage`, `ai.instructions.manage` | no |
| `admin.settings.audit` | `/admin/settings/audit` | `layouts.admin` | `system.read` | no |
| `admin.settings.group` | `/admin/settings/group/{group}` | `layouts.admin` | `settings.manage` | no |
| `admin.translations` | `/admin/settings/translations` | `layouts.admin` | `translations.manage` | no |
| `admin.themes` | `/admin/themes` | `layouts.admin` | `themes.view` | no |
| `admin.themes.create` | `/admin/themes/create` | `layouts.admin` | `themes.create`, `themes.code.manage` | yes |
| `admin.themes.edit` | `/admin/themes/{themeId}/edit` | `layouts.admin` | `themes.edit`, `themes.code.manage` | yes |
| `admin.users` | `/admin/users` | `layouts.admin` | `users.view` | no |
| `admin.users.create` | `/admin/users/create` | `layouts.admin` | `users.create`, `roles.manage` | no |
| `admin.users.edit` | `/admin/users/{userId}/edit` | `layouts.admin` | `users.edit` | no |
| `frontend.article.detail` | `/articles/{slug}` | - | - | no |
| `auth.register` | `/register/{token}` | - | - | yes |
<!-- /naf:auto:pages -->

### API Endpoints

<!-- naf:auto:api -->
<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->

| ID | Method | Path | Permissions |
| --- | --- | --- | --- |
| `admin.articles.image` | `POST` | `/admin/articles/image` | any of `media.upload`, `articles.edit` |
| `api.admin.ai.memory.append` | `POST` | `/api/admin/ai/memory` | `ai.instructions.manage` |
| `api.admin.ai.mistakes` | `POST` | `/api/admin/ai/mistakes` | `ai.instructions.manage` |
| `api.admin.ai.mistakes.delete` | `POST` | `/api/admin/ai/mistakes/delete` | `ai.instructions.manage` |
| `api.admin.ai.mistakes.review` | `GET` | `/api/admin/ai/mistakes/review` | `ai.instructions.manage` |
| `api.admin.ai.prompts` | `GET` | `/api/admin/ai/prompts` | `ai.instructions.manage` |
| `api.admin.ai.prompts.save` | `POST` | `/api/admin/ai/prompts` | `ai.instructions.manage` |
| `api.admin.ai.tools` | `GET` | `/api/admin/ai/tools` | `mcp.tools.call` |
| `api.admin.ai.tools.call` | `POST` | `/api/admin/ai/tools/call` | `mcp.tools.call` |
| `api.admin.ai.tools.select` | `POST` | `/api/admin/ai/tools/select` | `mcp.tools.call` |
| `api.admin.articles` | `GET` | `/api/admin/articles` | `articles.view` |
| `api.admin.articles.translate.enqueue` | `POST` | `/api/admin/articles/{articleId}/translate/enqueue` | `articles.create` |
| `api.admin.articles.delete` | `POST` | `/api/admin/articles/{articleId}/{language}/delete` | `articles.delete` |
| `api.admin.articles.next` | `GET` | `/api/admin/articles/{articleId}/{language}/next` | `articles.create` |
| `api.admin.articles.publish` | `POST` | `/api/admin/articles/{articleId}/{language}/publish` | `articles.publish` |
| `api.admin.articles.review` | `POST` | `/api/admin/articles/{articleId}/{language}/review` | any of `articles.review`, `articles.publish` |
| `api.admin.articles.version.delete` | `POST` | `/api/admin/articles/{articleId}/{language}/{version}/delete` | `articles.delete` |
| `api.admin.forms.delete` | `POST` | `/api/admin/forms/{id}/delete` | `forms.delete` |
| `api.admin.media` | `GET` | `/api/admin/media` | `media.view` |
| `api.admin.media.upload` | `POST` | `/api/admin/media/upload` | `media.upload` |
| `api.admin.media.delete` | `POST` | `/api/admin/media/{id}/delete` | `media.delete` |
| `api.admin.media.update` | `POST` | `/api/admin/media/{id}/update` | `media.edit` |
| `api.admin.navigation.delete` | `POST` | `/api/admin/navigation/{navigationId}/delete` | `navigation.delete` |
| `api.admin.navigation.publish` | `POST` | `/api/admin/navigation/{navigationId}/publish` | `navigation.edit` |
| `api.admin.page-layouts.store` | `POST` | `/api/admin/page-layouts` | any of `pages.create`, `pages.edit` |
| `api.admin.page-layouts.delete` | `DELETE` | `/api/admin/page-layouts/{id}` | `pages.delete` |
| `api.admin.pages.delete` | `POST` | `/api/admin/pages/{id}/delete` | `pages.delete` |
| `api.admin.rbac.permissions` | `GET` | `/api/admin/rbac/permissions` | `permissions.manage` |
| `api.admin.rbac.permissions.create` | `POST` | `/api/admin/rbac/permissions/create` | `permissions.manage` |
| `api.admin.rbac.permissions.delete` | `POST` | `/api/admin/rbac/permissions/delete` | `permissions.manage` |
| `api.admin.rbac.permissions.update` | `POST` | `/api/admin/rbac/permissions/update` | `permissions.manage` |
| `api.admin.rbac.roles` | `GET` | `/api/admin/rbac/roles` | `roles.manage` |
| `api.admin.rbac.roles.create` | `POST` | `/api/admin/rbac/roles/create` | `roles.manage` |
| `api.admin.rbac.roles.delete` | `POST` | `/api/admin/rbac/roles/delete` | `roles.manage` |
| `api.admin.rbac.roles.permissions` | `POST` | `/api/admin/rbac/roles/permissions` | `roles.manage`, `permissions.manage` |
| `api.admin.rbac.roles.update` | `POST` | `/api/admin/rbac/roles/update` | `roles.manage` |
| `api.admin.roles` | `GET` | `/api/admin/roles` | `roles.manage` |
| `api.admin.settings.update` | `POST` | `/api/admin/settings` | `settings.manage` |
| `api.admin.summary` | `GET` | `/api/admin/summary` | `admin.access` |
| `api.admin.themes.store` | `POST` | `/api/admin/themes` | `themes.code.manage` |
| `api.admin.translations.show` | `GET` | `/api/admin/translations/{language}` | any of `admin.access`, `pages.view`, `pages.create`, `pages.edit` |
| `api.admin.translations.update` | `POST` | `/api/admin/translations/{language}` | `translations.manage` |
| `api.admin.users` | `GET` | `/api/admin/users` | `users.view` |
| `api.admin.users.delete` | `POST` | `/api/admin/users/delete` | `users.delete` |
| `api.admin.users.invite` | `POST` | `/api/admin/users/invite` | `users.create`, `roles.manage` |
| `api.admin.users.list` | `GET` | `/api/admin/users/list` | `users.view` |
| `api.admin.users.password` | `POST` | `/api/admin/users/password` | `users.edit` |
| `api.admin.users.roles` | `POST` | `/api/admin/users/roles` | `users.edit`, `roles.manage` |
| `api.admin.users.store` | `POST` | `/api/admin/users/store` | `users.create`, `roles.manage` |
| `api.admin.users.update` | `POST` | `/api/admin/users/update` | `users.edit` |
| `api.forms.submit` | `POST` | `/api/forms/{identifier}/submit` | - |
| `media.serve` | `GET` | `/media/{publicId}/{filename}` | - |
<!-- /naf:auto:api -->

### Content Types

<!-- naf:auto:contenttypes -->
<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->

| ID | Label | Model class |
| --- | --- | --- |
| `article` | Article | `Naf\CMS\Models\Article` |
| `form` | Form | `Naf\CMS\Models\Form` |
| `media` | Media | `Naf\CMS\Models\Media` |
| `navigation` | Navigation | `Naf\CMS\Models\SiteNavigation` |
<!-- /naf:auto:contenttypes -->

### Permissions

<!-- naf:auto:permissions -->
<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->

| Permission | Description |
| --- | --- |
| `admin.access` | Access the admin area. |
| `settings.manage` | Manage application and plugin settings. |
| `translations.manage` | Manage translation overrides and language files. |
| `system.read` | Read system status and health information. |
| `users.view` | View users. |
| `users.create` | Create users. |
| `users.edit` | Edit users. |
| `users.delete` | Delete users. |
| `roles.manage` | Manage roles. |
| `permissions.manage` | Manage permissions. |
| `articles.view` | View articles. |
| `articles.create` | Create articles. |
| `articles.edit` | Edit articles. |
| `articles.review` | Review articles. |
| `articles.publish` | Publish articles. |
| `articles.schedule` | Schedule articles. |
| `articles.archive` | Archive articles. |
| `articles.delete` | Delete articles. |
| `pages.view` | View pages. |
| `pages.create` | Create pages. |
| `pages.edit` | Edit pages. |
| `pages.delete` | Delete pages. |
| `themes.view` | View themes. |
| `themes.create` | Create themes. |
| `themes.edit` | Edit themes. |
| `themes.delete` | Delete themes. |
| `themes.code.manage` | Manage themes code. |
| `navigation.view` | View navigation. |
| `navigation.create` | Create navigation. |
| `navigation.edit` | Edit navigation. |
| `navigation.delete` | Delete navigation. |
| `forms.view` | View forms. |
| `forms.create` | Create forms. |
| `forms.edit` | Edit forms. |
| `forms.delete` | Delete forms. |
| `forms.submissions.view` | View forms submissions. |
| `media.view` | View media. |
| `media.upload` | Upload media. |
| `media.edit` | Edit media. |
| `media.delete` | Delete media. |
| `queue.read` | Read queue. |
| `mcp.tools.call` | Call allowed MCP tools. |
| `ai.instructions.manage` | Manage ai instructions. |
<!-- /naf:auto:permissions -->

### MCP Tools

<!-- naf:auto:mcp -->
<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->

| Tool | Description |
| --- | --- |
| `cms_admin_navigate` | Navigates the current admin UI to a safe registered NafCMS admin area such as PageBuilder, articles, forms, themes, media, users, roles, navigation, or settings. |
| `cms_article_draft_append` | Appends plain text as paragraphs to an existing article draft, resolving the article by id, title, or slug. |
| `cms_article_draft_create` | Creates a new article draft with Editor.js-style content blocks. |
| `cms_article_lookup` | Finds latest article versions by title, slug, language, or status so later MCP actions can target an article id. |
| `cms_article_status_counts` | Returns latest article-version workflow status counts, optionally filtered by language. |
| `cms_audit_recent` | Returns recent CMS audit log events, a chronological activity log of admin actions and system changes, without exposing old/new value payloads. |
| `cms_content_overview` | Returns high-level CMS content counts across articles, taxonomy, pages, media, users, and audit entries. |
| `cms_media_summary` | Returns media library counts, byte totals, kind counts, and optional recent media items. |
| `cms_page_overview` | Returns page counts by status, language, template, and optional recent pages. |
| `cms_pagebuilder_inspect` | Starts the PageBuilder show mode so the user can click a visible block or nested element and return structured read-only information about it. |
| `cms_plugin_overview` | Returns loaded NAF plugins and registry counts relevant to NafCMS extensions. |
| `cms_queue_status` | Returns file-backed queue counts for pending, locked, and deadletter jobs. |
| `cms_system_health` | Returns current NafCMS system health data. The result includes php.version, php.memory_limit, database.available, database.driver, database.server_version, session storage settings, MCP auth token-file status, host runtime directories, published asset status, and loaded NAF plugins. When summarizing this tool result for a user, use the exact returned values and present all returned sections as compact Markdown tables. Use tables for PHP, database, session, MCP auth, storage, and plugins when those sections are present; do not render scalar diagnostics only as label-colon text when they can be represented as table rows. Do not say that PHP version, database version, modules, storage, or plugin data are missing when the returned JSON contains those fields. Do not invent modules or health checks that are not present in the result. |
| `cms_tools_search` | Searches the registered CMS tools for the current request and UI context. Use this when the currently visible tools do not clearly cover the user request. The result returns candidate tool definitions. It never answers the user request itself: always call one of the returned tools afterwards instead of describing them. |
| `cms_user_role_summary` | Returns user, role, permission, and assignment counts for the NafCMS RBAC model. |
| `external_air_quality` | Returns current air quality, UV index, and pollen context for a location so local businesses can plan outdoor, health, sport, and seasonal campaigns. |
| `external_exchange_rates` | Returns latest exchange rates and optional converted amounts for international shop pricing, ads, purchasing, and campaign planning. |
| `external_public_holidays` | Returns public holidays for a country and year so CMS or shop owners can plan campaigns, opening hours, staffing, and publishing schedules. |
| `external_trending_topics` | Returns current public-interest or news trend signals from public sources for content ideas, campaigns, and shop planning. |
| `external_weather_forecast` | Returns current weather and a short forecast for a location so CMS or shop owners can plan campaigns, events, and operational actions. |
<!-- /naf:auto:mcp -->

### Examples

<!-- naf:auto:examples -->
<!-- Automatically generated via `vendor/bin/naf docs:sync`; do not edit manually. -->

| Example | Shows |
| --- | --- |
| [01-page-with-permission](examples/01-page-with-permission/) | Shows how to register an admin page through `cms()->pages()`. |
| [02-custom-content-type](examples/02-custom-content-type/) | Shows a minimal content type backed by a NAF ORM model. |
| [03-block-type](examples/03-block-type/) | Shows how to register page-builder block metadata through `cms()->blockTypes()`. |
| [04-mcp-tool](examples/04-mcp-tool/) | Shows the small class pattern used by NafCMS MCP tools. |
| [05-complete-plugin](examples/05-complete-plugin/) | This compact example shows the explicit registration path for a Composer package of type `naf-plugin`: `bootstrap.php` registers a protected Page, API endpoint, migration path, and scoped MCP tool. NAF discovers the package's `src/views` directory by convention; the Page references that view explicitly. |
| [06-service-override](examples/06-service-override/) | NafCMS binds replaceable behavior to contracts during the plugin bootstrap. |
<!-- /naf:auto:examples -->

## Conventions And Security

Public extension points are listed in the Auto Reference above. Auth is enabled by default; permissions and MCP scopes should be named clearly, and tokens or API secrets must not be stored in plaintext.

For architecture principles, audit rules, testing expectations, and contributor AI-agent editing guidance, read [AGENTS.md](AGENTS.md). Release users should also review [the changelog](CHANGELOG.md), [v0.0.1 release notes](RELEASE_NOTES_0.0.1.md), and [the security policy](SECURITY.md); suspected vulnerabilities belong on the private path described there, not in a public issue.

## Authentication migration

Generic identity, provider and permission mechanics use `naf/auth`. See [the migration guide](docs/auth-migration.md) for published Composer dependencies, existing-session compatibility and application boundaries.

## PHP code style

Source, tests and PHP templates follow the shared [NAF code style](https://github.com/nafphp/docs/blob/main/CODE_STYLE.md)
(PER Coding Style 3.0 with the Nafinity readability rules). After `composer install`, run
`composer style:check` to verify formatting or `composer style:fix` to apply it. The formatter
is a development dependency. Review template output and run the package checks after changes.
