# NixCMS

NixCMS is a lightweight, extensible CMS built on top of NixPHP. It is bare-metal first: PHP 8.3+, Composer, server-rendered `.phtml` templates, Tailwind v4, and optional MCP/AI integration without a separate documentation-site toolchain.

## Quickstart

```bash
composer install
# Set CMS_SETUP_MODE=1 in .env.local for the first local setup.
bin/nix install:database
bin/nix user:create admin change-me admin@example.com admin
bin/nix mcp:token:create
composer serve
```

Open `http://127.0.0.1:8080/admin`, then disable `CMS_SETUP_MODE` again. Copy `.env.example` to `.env.local` first when your local database settings differ from the defaults. The webroot is `public`; production web servers should use it as the DocumentRoot and rewrite missing files to `index.php`.

Frontend assets are intentionally simple:

```bash
npm install
npm run build
```

Use `npm run dev` while working on Tailwind or admin UI assets.

## First Page

Register pages through the CMS registry. A plugin can run this from its bootstrap file:

```php
use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use function NixPHP\View\view;

cms()->pages()->add(
    Page::create('plugin.reports')
        ->setPath('/admin/reports')
        ->setTitle('Reports')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_VIEW)
        ->setContent(fn () => view('admin.reports.index'))
);
```

## How Do I Build...

- A permission-protected admin page: [examples/01-page-with-permission](examples/01-page-with-permission/)
- A custom content type: [examples/02-custom-content-type](examples/02-custom-content-type/)
- A page-builder block type: [examples/03-block-type](examples/03-block-type/)
- An MCP tool: [examples/04-mcp-tool](examples/04-mcp-tool/)

Run `composer test` after changing examples; the smoke test loads them against the CMS registries so stale docs fail like stale code.

## Auto Reference

These sections are generated from registries and enums. Edit the source code, then run `bin/nix docs:sync`.

### Public `cms()` Entry Points

<!-- nix:auto:cms -->
<!-- Automatically generated via `bin/nix docs:sync`; do not edit manually. -->

| Entry point | Purpose |
| --- | --- |
| `cms()->pages()` | Renderable HTTP pages and page data handlers. |
| `cms()->api()` | JSON/API/action endpoints. |
| `cms()->contentTypes()` | Registered CMS content types. |
| `cms()->content(Model::class)` | Resolve a content API by model class. |
| `cms()->articles()` | Convenience API for the Article content type. |
| `cms()->forms()` | Convenience API for the Form content type. |
| `cms()->media()` | Convenience API for the Media content type. |
| `cms()->navigation()` | Convenience API for published site navigation content. |
| `cms()->storage(Model::class)` | Criteria-based CRUD for one ORM entity. |
| `cms()->navigationRegistry()` | Named frontend and admin navigation metadata. |
| `cms()->visualizations()` | Registered chart definitions and rendering. |
| `cms()->blocks()` | Page-builder block type to view mappings. |
| `cms()->blockTypes()` | Page-builder block metadata for the admin builder. |
| `cms()->themes()` | Frontend theme assets and builder-visible surfaces. |
| `cms()->languages()` | Enabled, available, and discovered language codes. |
| `cms()->settings()` | Resolved runtime settings. |
| `cms()->settingsRegistry()` | Admin-editable setting sections and fields. |
| `cms()->stageTargets()` | AI/stage target resolvers for controlled staged changes. |
<!-- /nix:auto:cms -->

### Pages

<!-- nix:auto:pages -->
<!-- Automatically generated via `bin/nix docs:sync`; do not edit manually. -->

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
| `admin.settings` | `/admin/settings` | `layouts.admin` | `admin.access` | no |
| `admin.settings.ai` | `/admin/settings/ai` | `layouts.admin` | `admin.access` | no |
| `admin.settings.audit` | `/admin/settings/audit` | `layouts.admin` | `admin.access` | no |
| `admin.settings.group` | `/admin/settings/group/{group}` | `layouts.admin` | `admin.access` | no |
| `admin.translations` | `/admin/settings/translations` | `layouts.admin` | `admin.access` | no |
| `admin.themes` | `/admin/themes` | `layouts.admin` | `themes.view` | no |
| `admin.themes.create` | `/admin/themes/create` | `layouts.admin` | `themes.create` | yes |
| `admin.themes.edit` | `/admin/themes/{themeId}/edit` | `layouts.admin` | `themes.edit` | yes |
| `admin.users` | `/admin/users` | `layouts.admin` | `users.view` | no |
| `admin.users.create` | `/admin/users/create` | `layouts.admin` | `users.create` | no |
| `admin.users.edit` | `/admin/users/{userId}/edit` | `layouts.admin` | `users.edit` | no |
| `frontend.article.detail` | `/articles/{slug}` | - | - | no |
| `auth.register` | `/register/{token}` | - | - | yes |
<!-- /nix:auto:pages -->

### API Endpoints

<!-- nix:auto:api -->
<!-- Automatically generated via `bin/nix docs:sync`; do not edit manually. -->

| ID | Method | Path | Permissions |
| --- | --- | --- | --- |
| `admin.articles.image` | `POST` | `/admin/articles/image` | any of `media.upload`, `articles.edit` |
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
| `api.admin.rbac.roles.permissions` | `POST` | `/api/admin/rbac/roles/permissions` | `roles.manage` |
| `api.admin.rbac.roles.update` | `POST` | `/api/admin/rbac/roles/update` | `roles.manage` |
| `api.admin.roles` | `GET` | `/api/admin/roles` | `roles.manage` |
| `api.admin.settings.update` | `POST` | `/api/admin/settings` | `admin.access` |
| `api.admin.summary` | `GET` | `/api/admin/summary` | `admin.access` |
| `api.admin.translations.show` | `GET` | `/api/admin/translations/{language}` | any of `admin.access`, `pages.view`, `pages.create`, `pages.edit` |
| `api.admin.translations.update` | `POST` | `/api/admin/translations/{language}` | `admin.access` |
| `api.admin.users` | `GET` | `/api/admin/users` | `users.view` |
| `api.admin.users.delete` | `POST` | `/api/admin/users/delete` | `users.delete` |
| `api.admin.users.invite` | `POST` | `/api/admin/users/invite` | `users.create` |
| `api.admin.users.list` | `GET` | `/api/admin/users/list` | `users.view` |
| `api.admin.users.password` | `POST` | `/api/admin/users/password` | `users.edit` |
| `api.admin.users.roles` | `POST` | `/api/admin/users/roles` | `users.edit` |
| `api.admin.users.store` | `POST` | `/api/admin/users/store` | `users.create` |
| `api.admin.users.update` | `POST` | `/api/admin/users/update` | `users.edit` |
| `api.forms.submit` | `POST` | `/api/forms/{identifier}/submit` | - |
| `media.serve` | `GET` | `/media/{publicId}/{filename}` | - |
<!-- /nix:auto:api -->

### Content Types

<!-- nix:auto:contenttypes -->
<!-- Automatically generated via `bin/nix docs:sync`; do not edit manually. -->

| ID | Label | Model class |
| --- | --- | --- |
| `article` | Article | `NixCMS\Models\Article` |
| `form` | Form | `NixCMS\Models\Form` |
| `media` | Media | `NixCMS\Models\Media` |
| `navigation` | Navigation | `NixCMS\Models\SiteNavigation` |
<!-- /nix:auto:contenttypes -->

### Permissions

<!-- nix:auto:permissions -->
<!-- Automatically generated via `bin/nix docs:sync`; do not edit manually. -->

| Permission | Description |
| --- | --- |
| `admin.access` | Access the admin area. |
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
<!-- /nix:auto:permissions -->

### MCP Tools

<!-- nix:auto:mcp -->
<!-- Automatically generated via `bin/nix docs:sync`; do not edit manually. -->

| Tool | Description |
| --- | --- |
| `cms_admin_navigate` | Navigates the current admin UI to a safe registered NixCMS admin area such as PageBuilder, articles, forms, themes, media, or settings. |
| `cms_article_draft_append` | Appends plain text as paragraphs to an existing article draft, resolving the article by id, title, or slug. |
| `cms_article_draft_create` | Creates a new article draft with Editor.js-style content blocks. |
| `cms_article_lookup` | Finds latest article versions by title, slug, language, or status so later MCP actions can target an article id. |
| `cms_article_status_counts` | Returns latest article-version workflow status counts, optionally filtered by language. |
| `cms_audit_recent` | Returns recent CMS audit log events, a chronological activity log of admin actions and system changes, without exposing old/new value payloads. |
| `cms_content_overview` | Returns high-level CMS content counts across articles, taxonomy, pages, media, users, and audit entries. |
| `cms_media_summary` | Returns media library counts, byte totals, kind counts, and optional recent media items. |
| `cms_page_overview` | Returns page counts by status, language, template, and optional recent pages. |
| `cms_plugin_overview` | Returns loaded NixPHP plugins and registry counts relevant to NixCMS extensions. |
| `cms_queue_status` | Returns file-backed queue counts for pending, locked, and deadletter jobs. |
| `cms_stage_commit` | Commits a previously staged CMS change into stored CMS state. Does not publish. |
| `cms_stage_create_change` | Creates a staged CMS change for a target such as a page. Does not publish. |
| `cms_stage_preview` | Returns the staged working snapshot, change list, warnings, and UI actions for a CMS stage. |
| `cms_system_health` | Returns basic NixCMS health information for PHP, storage paths, and loaded NixPHP plugins. |
| `cms_tools_search` | Searches the registered CMS tools for the current request and UI context. |
| `cms_user_role_summary` | Returns user, role, permission, and assignment counts for the NixCMS RBAC model. |
| `external_air_quality` | Returns current air quality, UV index, and pollen context for a location so local businesses can plan outdoor, health, sport, and seasonal campaigns. |
| `external_exchange_rates` | Returns latest exchange rates and optional converted amounts for international shop pricing, ads, purchasing, and campaign planning. |
| `external_public_holidays` | Returns public holidays for a country and year so CMS or shop owners can plan campaigns, opening hours, staffing, and publishing schedules. |
| `external_trending_topics` | Returns current public-interest or news trend signals from public sources for content ideas, campaigns, and shop planning. |
| `external_weather_forecast` | Returns current weather and a short forecast for a location so CMS or shop owners can plan campaigns, events, and operational actions. |
<!-- /nix:auto:mcp -->

### Examples

<!-- nix:auto:examples -->
<!-- Automatically generated via `bin/nix docs:sync`; do not edit manually. -->

| Example | Shows |
| --- | --- |
| [01-page-with-permission](examples/01-page-with-permission/) | Shows how to register an admin page through `cms()->pages()`. |
| [02-custom-content-type](examples/02-custom-content-type/) | Shows a minimal content type backed by a NixPHP ORM model. |
| [03-block-type](examples/03-block-type/) | Shows how to register page-builder block metadata through `cms()->blockTypes()`. |
| [04-mcp-tool](examples/04-mcp-tool/) | Shows the small class pattern used by NixCMS MCP tools. |
<!-- /nix:auto:examples -->

## Conventions And Security

Public extension points are listed in the Auto Reference above. Auth is enabled by default; permissions and MCP scopes should be named clearly, and tokens or API secrets must not be stored in plaintext.

For architecture principles, audit rules, testing expectations, and AI-agent editing guidance, read [AGENTS.md](AGENTS.md).
