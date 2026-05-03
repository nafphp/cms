# NixCMS Vision

NixCMS is intended to become a lightweight, extensible CMS built on top of NixPHP. It follows the same philosophy as the framework: pragmatic, low-ceremony, native PHP where possible, and flexible extension through small focused plugins.

## Core Idea

NixCMS is not meant to be a heavy monolithic CMS. It is a management layer on top of NixPHP. The core provides shared CMS building blocks, while domain-specific functionality can be added through plugins.

The system should start small but be able to grow cleanly:

- Articles and content
- Users, roles, and permissions
- Media management
- Localization
- Workflows such as draft, review, publish, and schedule
- Jobs, scheduling, and background processing
- AI and MCP integration
- Plugin-capable admin interfaces

## Architecture Principles

- NixPHP remains the foundation.
- NixCMS adds CMS conventions and administration features without making the framework heavy.
- Features should preferably be implemented as plugins or clearly separated modules.
- Avoid unnecessary abstractions just because frameworks usually have them.
- The core should only contain functionality that most CMS installations genuinely share.
- Extensions should be able to register plain PHP classes, views, commands, migrations, tools, and services.

## Admin UI

The administration area should feel like a modern, calm enterprise application while staying technically lightweight.

It should support:

- Dashboard
- Resource lists
- Forms
- Modals
- Tables
- Filters and search
- Language switching
- Roles and permissions
- Plugin navigation
- Status and health views

The UI should be functional and dense enough for real work, not feel like a marketing page.

Admin UI implementation should be Tailwind-first:

- Prefer Tailwind utility classes directly in `.phtml` components and views for layout, spacing, typography, borders, colors, responsive behavior, and common states.
- Use `app/Resources/css/nixcms.css` primarily as the Tailwind CSS entry point for `@import`, `@source`, `@theme`, dark-mode variants, global tokens, base behavior, and third-party or genuinely complex selectors.
- Add custom CSS only when Tailwind utility classes would make the markup hard to read or when the behavior needs selectors/pseudo-elements/custom properties that Tailwind utilities cannot express cleanly.
- Do not add a `tailwind.config.js` by default; keep Tailwind v4 configuration in the CSS entry point unless a real toolchain constraint requires otherwise.

## Plugin Concept

NixPHP plugins should also be able to extend NixCMS.

A plugin may optionally provide:

- Routes
- Controllers
- Views
- Assets
- Migrations
- Commands
- Services
- Admin menu entries
- Permissions
- Jobs
- Scheduler entries
- MCP tools
- AI features

The goal: anyone who can write NixPHP plugins should be able to extend CMS functionality.

## MCP and AI

NixCMS should be designed with AI support from the beginning.

MCP tools can expose controlled CMS capabilities, for example:

- Read article status counts
- Check system health
- Inspect queue status
- Create article drafts
- Translate content
- Summarize content
- Suggest review improvements
- Inspect users or roles
- Suggest meaningful next actions

Important: MCP tools are concrete executable capabilities. A future capability overview may be derived from them, but information should not be maintained twice.

## Capability Concept

If NixCMS later gets a capability registry, the tool or plugin system should remain the source of truth wherever possible.

Not ideal:

- Register a tool once
- Describe the same capability again separately in a registry

Better:

- Tools, plugins, or modules may optionally provide metadata
- `cms_capabilities` can derive an overview from that metadata
- Admin UI, CLI, docs, and AI clients can use the same information

## Auth and Security

NixCMS should have secure defaults.

- Auth is enabled by default.
- Internal or open modes may be configurable, but must be explicit.
- Tokens and API access secrets should not be stored in plaintext.
- File-based auth can be enough for small installations.
- Database-backed auth can be added later.
- Permissions should use clear permission names.
- MCP tools should support scopes and permissions.

## Development Style

When building NixCMS:

- Build simple real workflows first.
- Abstract only after patterns become visible.
- Avoid architecture for its own sake.
- Work in small, testable steps.
- Prefer real admin requirements over theoretical elegance.
- Keep plugin compatibility in mind early.
- Treat AI support as a native extension, not an afterthought.

## Testing

- Run `composer test` for the PHPUnit suite.
- Keep fast unit tests as the default safety net.
- Extend `Tests\Support\NixPHPTestCase` for unit tests that need common CMS fixtures such as `mockArticle()`, `mockArticleVersion()`, or Editor.js block payload helpers.
- Use `Tests\Support\DatabaseTestCase` for DB-shaped service or repository tests that can run against an in-memory SQLite database.
- Prefer injecting a `PDO` into query-oriented services for tests, while production code may continue to resolve the configured NixPHP database connection.
- Do not require Docker or a local MySQL server for the default test path.

## Development Environment Versus Shipped Project

This repository is the NixCMS development environment. It may contain planning notes, local IDE metadata, exploratory docs, and implementation guidance that help build the product.

What gets committed to the actual distributable NixCMS project should be treated as the user-facing product:

- Keep committed files useful for people installing, extending, or auditing NixCMS.
- Avoid shipping local-only assumptions from this workstation.
- Do not require Docker for the primary development path.
- Prefer a bare-metal-first setup using PHP 8.3+, Composer, the built-in PHP server, and local CLI commands.
- Add Docker as a second setup path later, with a ready-to-run `docker compose up -d` experience.
- Keep both setup paths behaviorally equivalent: same migrations, same env names, same CLI commands, same public entry point.
- Do not let Docker-specific paths, hostnames, ports, or service names leak into core application code.

There should eventually be two kinds of project guidance:

- Development guidance for contributors building NixCMS itself.
- Shipped guidance for users and AI agents working inside an installed NixCMS project.

The shipped project should include its own `AGENTS.md` that explains the application structure, extension points, commands, conventions, and safe editing rules from the perspective of someone using NixCMS, not building the NixCMS source distribution.

## CMS API Surface

NixCMS provides a public API surface through the `cms()` helper function, which returns a singleton `NixCMS\Support\Cms` instance.

### Entry Points

The main entry points are:

- `cms()->pages()` — returns the `PageRegistry`; source of truth for renderable HTTP pages, including path, title, layout, auth, role/permission requirements, optional data handlers, and content callbacks.
- `cms()->api()` — returns the `ApiRegistry`; source of truth for JSON/API endpoints, including path, method, auth, role/permission requirements, and a handler that returns a PSR response.
- `cms()->contentTypes()` — returns the `ContentRegistry`; source of truth for registered content types such as Article.
- `cms()->content(Article::class)` — resolves the registered content type for the model and returns its content API.
- `cms()->articles()` — convenience doorway to the registered Article content type API. Do not treat articles as a special HTTP architecture category; they are the first registered content type.
- `cms()->forms()` — convenience doorway to the registered Form content type API.
- `cms()->media()` — convenience doorway to the registered Media content type API.
- `cms()->navigation()` — convenience doorway to the registered SiteNavigation content type API.
- `cms()->storage(Model::class)` — returns an `EntityStorage` for Criteria-based model reads and ORM-backed writes above `db()`/PDO and below domain APIs such as `cms()->media()`.
- `cms()->navigationRegistry()` — returns the `NavigationRegistry`; source of truth for named frontend/admin navigation trees.
- `cms()->visualizations()` — returns the `VisualizationRegistry`; source of truth for registered chart definitions and chart rendering.
- `cms()->blocks()` — returns the `BlockViewRegistry`; source of truth for page-builder block type to view mappings.
- `cms()->languages()` — returns the `LanguageRegistry`; source of truth for enabled, available, and discovered language codes.
- `cms()->settings()` — returns the runtime `Settings` helper for reading resolved setting values.
- `cms()->settingsRegistry()` — returns the `SettingsRegistry`; source of truth for admin-editable setting sections and fields.

### Examples

Create a draft article:

```php
$result = cms()->content(Article::class)->create([
    'title' => 'My Article',
    'slug' => 'my-article',
    'content' => ['blocks' => [...]],
    'language' => 'de',
], $authorId);

echo $result['articleId'];
echo $result['articleVersionId'];
```

Publish an article:

```php
cms()->content(Article::class)->publish($articleId, 'de');
```

Register a renderable page:

```php
cms()->pages()->add(
    Page::create('admin.users')
        ->setPath('/admin/users')
        ->setTitle('Users')
        ->setLayout('admin')
        ->requirePermission(Permission::USERS_VIEW)
        ->setContent(fn () => view('admin.users.index', [
            'users' => repo(UserRepository::class)->findAllWithRolesAndPermissions(),
        ]))
);
```

Register a page with a POST data handler on the same URL:

```php
Page::create('admin.users.create')
    ->setPath('/admin/users/create')
    ->setTitle('Create user')
    ->setLayout('admin')
    ->requirePermission(Permission::USERS_CREATE)
    ->setDataHandler(StoreUser::class, 'POST', Permission::USERS_CREATE)
    ->setContent(fn () => view('admin.users.form', ['user' => null]));
```

Register an API endpoint:

```php
cms()->api()->add(
    ApiEndpoint::post('api.admin.users.store', '/api/admin/users/store')
        ->requirePermission(Permission::USERS_CREATE)
        ->setHandler(StoreUser::class)
);
```

Register a content type:

```php
cms()->contentTypes()->add(new ArticleContentType());
```

Use a registered content type to create, restore, and delete versions:

```php
$contentApi = cms()->content(Article::class);

// Create a new draft
$result = $contentApi->create(['title' => 'Draft', ...], $authorId);

// Restore a specific version as a new draft
$restored = $contentApi->restoreVersion($articleId, 'de', $versionNumber);

// List versions
$versions = $contentApi->versions($articleId);

// Delete a language or specific version
$contentApi->deleteLocale($articleId, 'de');
$contentApi->deleteVersion($articleId, 'de', $versionNumber);
```

Create a form draft through the registered Form content type:

```php
$formId = cms()->forms()->save([
    'title' => 'Contact',
    'fields' => [['type' => 'email', 'name' => 'email']],
]);
```

Store media through the registered Media content type:

```php
$media = cms()->media()->storeUploadedFile($uploadedFile, $authorId, [
    'alt' => 'Dashboard preview',
]);
```

Search CMS model storage with Criteria:

```php
$media = cms()->storage(Media::class)->search(
    Criteria::create()
        ->equals('visibility', 'public')
        ->contains('original_name', 'dashboard')
        ->sort('created_at', 'DESC')
        ->limit(20)
);
```

Create or update CMS model storage through the NixPHP ORM layer:

```php
$media = cms()->storage(Media::class)->create([
    'public_id' => 'generated-id',
    'visibility' => 'public',
    'original_name' => 'dashboard.png',
    'stored_name' => 'dashboard.png',
    'storage_path' => 'media/dashboard.png',
    'file_path' => 'media/dashboard.png',
    'public_url' => '/media/dashboard.png',
    'mime_type' => 'image/png',
    'extension' => 'png',
    'size_bytes' => 12345,
]);

cms()->storage(Media::class)->update($media->getId(), [
    'alt_text' => 'Dashboard preview',
]);
```

Publish a named site navigation through the registered navigation content API:

```php
cms()->navigation()->save([
    'identifier' => 'main',
    'title' => 'Main navigation',
    'items' => [['label' => 'Home', 'url' => '/']],
]);
```

Register frontend navigation metadata:

```php
cms()->navigationRegistry()->addNavigation(
    Navigation::create('footer', 'Footer navigation')
);
```

Register and render a chart:

```php
cms()->visualizations()->add(ChartDefinition::bar('content-status', [
    'Draft' => 4,
    'Published' => 12,
]));
```

Register a page-builder block view:

```php
cms()->blocks()->add('mycustom', 'page.blocks.mycustom');
echo cms()->blocks()->viewFor('mycustom');
```

List language options for admin selectors:

```php
$options = cms()->languages()->options();
echo $options['de'] ?? 'DE';
```

Read a runtime setting:

```php
$siteName = cms()->settings()->value('app.name', 'NixCMS');
echo $siteName;
```

Register an admin settings section:

```php
cms()->settingsRegistry()->add(
    SettingSection::create('site', 'Site')
);
```

### HTTP Registries

Follow these rules when extending HTTP behavior:

**Pages**:
- Register renderable UI resources through `cms()->pages()`.
- Keep `app/pages.php` as the visible page-definition aggregator and split area-specific definitions into `app/Resources/pages/*.php`.
- Use `Page::setDataHandler($handler, $method = 'POST', ?Permission $permission = null)` when a form posts back to the same UI resource.
- Data handlers must return a real PSR response, usually `json()`, `response()`, or `redirect()`.
- Put page/form data handlers under `app/Handlers/Page/<Area>/`, for example `LoginUser`, `RegisterInvitedUser`, or `StoreArticleDraft`.
- Keep page definitions as route/auth/content wiring. Move substantial workflows into services or handlers.

**API endpoints**:
- Register JSON/API/action endpoints through `cms()->api()`.
- Keep `app/api.php` as the visible API-definition aggregator and split area-specific definitions into `app/Resources/api/*.php`.
- Use `ApiEndpoint::get/post/put/delete(...)` and `setHandler(...)`; handlers must return a PSR response.
- Put API execution logic in invokable handler classes under `app/Handlers/Api/<Area>/`, for example `StoreUser`, `PublishArticle`, or `UpdateRolePermissions`.
- Do not add new `Controllers\Api` classes for registry-backed endpoints; the registry-owned `ApiController` is the HTTP entry point.
- Prefer permissions over roles for API authorization. Roles are supported but should be used sparingly.
- API endpoint metadata should become a source for future capability/capability-overview work rather than duplicating static capability lists.

**Routing**:
- `app/routes.php` should stay small: load page/API definitions, then call `cms()->pages()->registerRoutes()` and `cms()->api()->registerRoutes()`.
- Plugins should register pages and endpoints through the same registries. App code may override registered entries with explicit replacement where supported.
- Keep `app/Controllers` small. The normal CMS path should need only central HTTP dispatchers such as `Controllers\Page\PageController` and `Controllers\Api\ApiController`; put workflow behavior in page/API handlers or services.

### Content Type Registry

- Register content types through `cms()->contentTypes()`.
- Define core content types in `app/content.php`.
- Put content-type-specific logic under `app/Content/<Type>/`, for example `app/Content/Article/ArticleContentType.php`.
- A content type should describe its ID, label, model class, API object, and type-specific helpers such as form/catalog/rendering services.
- Article Editor.js block renderers live under `app/Content/Article/Block` and are registered through `NixCMS\Content\Article\BlockRegistry`.
- Do not add global CMS block registries for article-specific rendering; expose block extension through the Article content type.
- Pages and API endpoints should reach article form/catalog/rendering behavior through the Article content type instead of directly creating scattered article services.
- Treat articles as the first content type, not as a special HTTP architecture.
- Prefer `cms()->content(Article::class)` for lifecycle operations.
- Keep `cms()->articles()` as a convenience doorway to `cms()->content(Article::class)` unless a repeated cross-type pattern proves a better abstraction.
- Do not add a type alias before its model exists.
- Abstract `ContentApi` only after at least two real content types reveal a shared shape.

### Block View Registry

- Register page-builder block view mappings through `cms()->blocks()`.
- Core defaults live in `NixCMS\Content\BlockViewRegistry`; plugins may call `cms()->blocks()->add('mycustom', 'page.blocks.mycustom')` during bootstrap.
- Keep aliases explicit in the registry constructor so agents can grep which block types share a view.
- Unknown block types must fall back to `page.blocks.placeholder`.
- Block views live under `views/page/blocks`; files directly inside that directory should be renderable blocks, while shared helpers belong in `views/page/blocks/_helpers`.

## Service Design Rules

Services should be targeted, reusable, and easy for humans and AI models to understand quickly.

Service rules:

- A service should represent one clear domain action or small cluster of related domain actions.
- Prefer names that explain the business capability, for example `ArticlePublishingService`, `PasswordResetService`, or `PluginNavigationService`.
- Avoid generic dumping grounds such as `CmsService`, `AdminService`, `HelperService`, or `Manager` classes unless the surrounding code has made a narrower meaning obvious.
- Keep services framework-light. Accept plain values, entities, repositories, and small collaborators rather than pulling global state from everywhere.
- Keep orchestration in services and persistence queries in repositories.
- Keep controllers thin: validate request shape, call services/repositories, choose response/view.
- Keep models focused on entity behavior and simple derived state, not multi-step workflows.
- Keep services reusable across HTTP controllers, CLI commands, queue jobs, scheduler tasks, and MCP tools.
- Avoid service methods that secretly depend on a current request unless the service is explicitly HTTP-facing.
- Prefer explicit method arguments over hidden session/config access when it makes behavior easier to test.
- Return small result objects or arrays when a workflow has meaningful status, warnings, or next actions.
- Throw domain-specific exceptions for expected failure cases that callers can handle.
- Do not introduce interfaces until there are multiple implementations, a test boundary needs one, or plugin integration clearly benefits from one.

Good service candidates:

- Article publishing, scheduling, version creation, review decisions, translation enqueueing.
- User invites, password changes, session revocation, role assignment.
- Media upload normalization, metadata extraction, public URL generation.
- Plugin admin menu collection and permission registration.
- MCP capability/tool summaries derived from registered tools and plugins.

Poor service candidates:

- Thin wrappers around one repository call.
- Shared bags of unrelated helper functions.
- Classes named after UI pages rather than reusable capabilities.
- Services that mix article workflows, user workflows, plugin registration, and rendering concerns.

## AI-Readable Codebase Rules

NixCMS should be easy for AI coding agents to inspect without needing tribal knowledge.

Structure and naming should favor discoverability:

- Keep namespaces aligned with directories.
- Keep feature folders predictable: Controllers, Models, Repositories, Services, Commands, Jobs, Migrations, Mcp/Tools, Resources, and views.
- Use explicit class and method names over clever abbreviations.
- Put domain enums in one obvious place.
- Put permissions and workflow statuses in typed enums where practical.
- Keep route names stable and descriptive.
- Keep config keys documented through defaults and examples.
- Prefer small files with clear responsibilities over large files with many unrelated concerns.
- Avoid magic registration that cannot be found with text search.
- If something registers routes, commands, jobs, scheduler entries, permissions, admin navigation, or MCP tools, make that registration grep-friendly.
- Add short comments only where they explain non-obvious domain intent, operational constraints, or extension behavior.

Every shipped NixCMS app should make the common AI inspection path obvious:

- Read `AGENTS.md` first.
- Inspect `composer.json` for packages and autoloading.
- Inspect `app/config.php` or equivalent config defaults.
- Inspect `app/routes.php` to see registry bootstrapping.
- Inspect `app/pages.php` and `app/Resources/pages` for renderable UI pages.
- Inspect `app/api.php` and `app/Resources/api` for JSON/API/action endpoints.
- Inspect `app/content.php` and `app/Content` for registered content types and content-specific helpers.
- Inspect `app/Migrations` for schema.
- Inspect `app/Controllers`, `app/Services`, `app/Repositories`, and `app/Models` for handlers and domain behavior.
- Inspect `app/Mcp/Tools` for AI/MCP capabilities.
- Inspect `views` and `app/Resources` for admin UI.

## Shipped AGENTS.md Expectations

The `AGENTS.md` shipped with generated or installed NixCMS projects should be concise, practical, and oriented around safe modification.

It should explain:

- What NixCMS is and which parts are core versus plugins.
- The folder structure and where common changes belong.
- The bare-metal local run path.
- The Docker run path once available.
- Migration, test, asset build, queue, scheduler, and MCP commands.
- How pages, page data handlers, API endpoints, views, services, repositories, and models are organized.
- How plugins extend page/API registries, views, commands, migrations, jobs, scheduler entries, admin navigation, permissions, and MCP tools.
- Which files are generated build output and should not be edited manually.
- Security expectations around auth, permissions, scopes, secrets, and tokens.
- The rule that MCP tools are executable capabilities and capability overviews should be derived from tool/plugin metadata wherever possible.

It should not include:

- Local workstation paths.
- Internal planning notes.
- Experimental ideas that are not present in the shipped code.
- Instructions that only make sense in this development repository.

## Long-Term Goal

NixCMS should become a standalone product built on NixPHP and powered by its plugin ecosystem.

It should feel:

- Lean like a custom framework project
- Extensible like a plugin system
- Productive like a modern CMS
- AI-ready from day one

Working name: **NixCMS**.

## Researched NixPHP Foundation

NixCMS should start from the official NixPHP package ecosystem instead of copying a full-stack CMS architecture from elsewhere.

Current research sources:

- GitHub/Packagist source package: `nixphp/framework` (`https://github.com/nixphp/framework`, `https://packagist.org/packages/nixphp/framework`)
- NixPHP package overview: `https://packagist.org/packages/nixphp/`
- Individual package sources use the `https://github.com/nixphp/<package>` pattern where published, for example `nixphp/database`, `nixphp/view`, and `nixphp/i18n`.

NixPHP core provides routing, dispatching, PSR-oriented infrastructure, plugin loading, configuration merging, view path registration, and bootstrap hooks. Everything beyond the smallest HTTP core should remain optional through Composer packages or NixCMS modules/plugins.

Recommended initial Composer base for NixCMS:

- `nixphp/framework`: required foundation.
- `nixphp/view`: required for the admin UI and server-rendered CMS screens.
- `nixphp/database`: required once content, users, sessions, or workflows are persisted.
- `nixphp/orm`: useful for the first CMS entities and repositories, especially content, users, roles, and permissions.
- `nixphp/session`: required for admin authentication and account workflows.
- `nixphp/form`: useful for form memory, validation-adjacent workflows, and admin forms.
- `nixphp/i18n`: required early because NixCMS should support localized content and a multilingual admin UI.
- `nixphp/cli`: required for migrations, user creation, maintenance commands, and operational tooling.
- `nixphp/queue`: required for translation, AI, media processing, publication tasks, and async maintenance.
- `nixphp/schedule`: required for scheduled publishing, recurring maintenance, and queue ticker workflows.
- `nixphp/mail`: useful for invites, password reset, workflow notifications, and publication/review notifications.
- `nixphp/client`: useful for external APIs, AI providers, webhooks, and integration plugins.
- `nixphp/mcp`: used successfully in WeOnlyWalk for concrete AI/MCP tools; keep it in scope for NixCMS even if its public package metadata is less visible than the other packages.

Recommended dependency phases:

1. Bootstrap phase: `framework`, `view`, `form`.
2. CMS core phase: `database`, `orm`, `session`, `i18n`, `cli`.
3. Operational phase: `queue`, `schedule`, `mail`.
4. AI/integration phase: `client`, `mcp`.

## NixPHP Plugin Rules For NixCMS

NixPHP plugins are Composer packages with package type `nixphp-plugin`. A plugin can provide a `bootstrap.php`, app configuration, views, page/API registrations, services, commands, tools, and other app-like files.

For NixCMS, preserve that model:

- A NixCMS feature should be a plugin or clearly isolated module unless it is truly shared CMS core.
- Plugins should register capabilities through executable code, not duplicated static registries.
- Plugin configuration should merge cleanly into app config.
- Plugin views should be added to the view search path rather than copied into the app.
- Plugin bootstrap code may register pages, API endpoints, commands, events, scheduler entries, queue jobs, MCP tools, admin navigation, and permissions.
- NixCMS-specific plugin metadata may be added later, but executable plugin/tool definitions should remain the source of truth wherever possible.

## WeOnlyWalk As Reference Material

The project `/Users/flo/PhpStormProjects/weonlywalk` is the main reference implementation. It should be mined for proven patterns, not copied wholesale.

Useful WeOnlyWalk elements:

- Composer stack: uses `nixphp/framework`, `form`, `database`, `view`, `i18n`, `orm`, `session`, `cli`, `schedule`, `mail`, and `mcp`.
- Project shape: `app/app` for application classes, `app/views` for `.phtml` templates, `app/public` for webroot/build output, `app/app/Resources` for CSS, JS, language files, HTTP snippets, and prompts.
- MVC organization: Controllers for Web/Admin/API, Models for ORM entities, Repositories for query logic, Services for orchestration, Commands for CLI, Jobs for queue tasks, Migrations for schema.
- Admin routing split: `/admin/...` for screens and `/api/...` or POST admin routes for actions.
- Content model: `articles` plus `article_versions`, where versions carry language, version number, workflow status, slug, title, teaser, content JSON, publish timestamp, and created timestamp.
- Localization model: language exists at the article-version level; WeOnlyWalk currently uses `de`, `en`, `fr`, `es`, and `it`.
- Workflow statuses: `draft`, `review`, `declined`, `published`, `scheduled`, and `archived`.
- Taxonomy model: `categories`, `tags`, and pivot table `article_tags`.
- RBAC model: `users`, `roles`, `permissions`, `user_role`, and `role_permissions`.
- Session model: database-backed `sessions` table is useful for admin account management and system health visibility.
- Invite/account pattern: `user_invites` is useful as a simple file-light onboarding flow for early installations.
- Admin UI pattern: layout, header, side menu, dashboard, dense tables, forms, settings pages, dark mode, and reusable form components.
- Frontend build pattern: Tailwind CSS plus semantic CSS in `app/app/Resources/css/nixphp.css`; built output in `app/public/css/nixphp.css`.
- Editor pattern: Editor.js-based content JSON is useful as a starting point for article content blocks.
- MCP pattern: tools are small PHP classes implementing `ToolInterface`; scope-aware tools implement `ScopedToolInterface`; schemas are defined with `NixPHP\MCP\Support\Schema`.
- Existing MCP ideas: `article_status_counts`, `system_health`, and `queue_status` are directly relevant to NixCMS.
- Operational learning: scheduled jobs may enqueue work while queue workers execute it; health views and MCP tools should expose pending/deadletter job counts.

Avoid carrying over WeOnlyWalk-specific features into NixCMS core:

- YouTube publishing and channel statistics.
- WeOnlyWalk-specific prompt files and generated YouTube metadata rules.
- Route/location-specific web research logic.
- Project branding, copy, and editorial assumptions.

These can later become example plugins or separate integration plugins.

## Suggested NixCMS Core Modules

Start with small modules that match real admin needs:

- `NixCMS\Admin`: dashboard, navigation, layout, health/status panels.
- `NixCMS\Auth`: login, logout, sessions, password hashing, invites, explicit auth modes.
- `NixCMS\Users`: users, roles, permissions, account management.
- `NixCMS\Content`: articles, versions, statuses, preview, publish, schedule, archive.
- `NixCMS\Taxonomy`: categories and tags.
- `NixCMS\Localization`: languages, translation helpers, language switching.
- `NixCMS\Media`: uploads, media metadata, image handling.
- `NixCMS\Jobs`: queue and scheduler integration.
- `NixCMS\Mcp`: scope-aware tools exposing concrete CMS operations.
- `NixCMS\Plugins`: admin navigation, permission, route, migration, command, job, scheduler, asset, and MCP registration points.

Keep module boundaries plain PHP at first. Extract to Composer plugins when the seams are proven by use.

## Suggested Initial Schema

Use WeOnlyWalk as a schema reference, but make names and columns product-neutral:

- `users`: provider/provider_id optional, username, email, password hash, names, timestamps.
- `roles`: unique role name.
- `permissions`: unique permission name.
- `user_role`: user-role pivot.
- `role_permissions`: role-permission pivot.
- `sessions`: database-backed session storage for admin visibility.
- `articles`: author, category, featured flag or priority fields, created timestamp.
- `article_versions`: article id, language, version, status, slug, title, teaser, content JSON, publish/schedule timestamps, created timestamp.
- `article_review_actions`: optional audit trail for review, decline, publish, and archive decisions.
- `categories`: title/name, slug, timestamps.
- `tags`: title/name, slug, timestamps.
- `article_tags`: article-tag pivot.
- `media`: file path, public URL, mime type, size, dimensions, alt text, owner, timestamps.
- `jobs` or file-backed queue storage, depending on the selected NixPHP queue driver.

Prefer migrations over schema dumps. Keep first migrations readable and reversible.

## Suggested Permission Names

Use clear dotted permission names:

- `admin.access`
- `system.read`
- `plugins.manage`
- `users.view`
- `users.create`
- `users.edit`
- `users.delete`
- `roles.manage`
- `permissions.manage`
- `articles.view`
- `articles.create`
- `articles.edit`
- `articles.review`
- `articles.publish`
- `articles.schedule`
- `articles.archive`
- `articles.delete`
- `media.view`
- `media.upload`
- `media.edit`
- `media.delete`
- `queue.read`
- `mcp.tools.call`

MCP scopes should intentionally map to permissions where possible, for example `articles:read`, `system:read`, `queue:read`, and future write scopes such as `articles:write`.

## Suggested First MCP Tools

Build MCP support as concrete capabilities from day one:

- `cms_system_health`: database, storage, PHP version, loaded plugins, session storage, and writable paths.
- `cms_queue_status`: pending jobs, deadletter jobs, active workers, channels.
- `cms_article_status_counts`: status counts by language based on latest article versions.
- `cms_plugin_overview`: loaded NixPHP/NixCMS plugins and their declared admin/capability metadata.
- `cms_user_role_summary`: role and permission counts, optionally scoped to admin-readable users.

Do not maintain a separate static capability list unless it is derived from tool/plugin metadata.

## Admin UI Direction From WeOnlyWalk

The NixCMS admin should use the dense enterprise direction already proven in WeOnlyWalk:

- Server-rendered `.phtml` views first.
- Calm layout with persistent admin navigation.
- Tables for resources, filters, search, status chips, and bulk-friendly actions.
- Forms and modals for common actions.
- Semantic CSS for reusable admin structures; Tailwind utilities for local one-off layout details.
- Dark mode support through a root `dark` class.
- Build assets from `app/app/Resources` into `app/public`; do not manually edit build output.

Avoid landing-page or marketing-style admin screens. The first screen should be useful as an operational dashboard.

For this NixCMS repository, browser-consumed assets must exist below `public/css` and `public/js`. `app/Resources` is for source/build inputs only.

Admin UI components should be self-contained by default. Prefer Tailwind utility classes and small local class decisions inside `views/components/admin/*`; keep global CSS for Tailwind entrypoint concerns, tokens, base behavior, complex selectors, and third-party integration only. A plugin should usually be able to override one component file without also patching global CSS.

## Operational Notes To Carry Forward

- Treat queue deadletter state as first-class observability.
- Long-lived workers can hold stale database connections; support worker lifecycle limits such as max jobs and max runtime.
- Scheduled tasks should be visible separately from queued jobs when possible.
- Health checks should cover database connectivity, storage writability, loaded plugins, session backend, queue state, and MCP token/auth status.
- Auth should be on by default; open/internal modes must be explicit.
- Tokens and external API credentials must not be stored in plaintext.

## First Build Target

The first useful NixCMS milestone should be:

1. NixPHP app skeleton with admin layout.
2. Database migrations for users, roles, permissions, sessions, articles, article versions, categories, tags, and pivots.
3. Login/logout plus a CLI command to create the first admin user.
4. Admin dashboard with health, content status counts, queue status, and loaded plugin overview.
5. Article list, create/edit draft, preview, review, publish, schedule, archive.
6. Language-aware article versions.
7. Three MCP tools: system health, queue status, article status counts.
