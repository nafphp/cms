# NafCMS Vision

NafCMS is intended to become a lightweight, extensible CMS built on top of NAF. It follows the same philosophy as the framework: pragmatic, low-ceremony, native PHP where possible, and flexible extension through small focused plugins.

## Core Idea

NafCMS is not meant to be a heavy monolithic CMS. It is a management layer on top of NAF. The core provides shared CMS building blocks, while domain-specific functionality can be added through plugins.

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

- NAF remains the foundation.
- NafCMS adds CMS conventions and administration features without making the framework heavy.
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
- Use `app/Resources/css/nafcms.css` primarily as the Tailwind CSS entry point for `@import`, `@source`, `@theme`, dark-mode variants, global tokens, base behavior, and third-party or genuinely complex selectors.
- Add custom CSS only when Tailwind utility classes would make the markup hard to read or when the behavior needs selectors/pseudo-elements/custom properties that Tailwind utilities cannot express cleanly.
- Do not add a `tailwind.config.js` by default; keep Tailwind v4 configuration in the CSS entry point unless a real toolchain constraint requires otherwise.

## Plugin Concept

NAF plugins should also be able to extend NafCMS.

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

The goal: anyone who can write NAF plugins should be able to extend CMS functionality.

## MCP and AI

NafCMS should be designed with AI support from the beginning.

**Important AI output rule: do not add separate code paths that semantically validate, correct, rewrite, or replace model output. Make the model input, tool metadata, routing context, and tool-result prompts robust enough that the model has no reasonable path except producing the correct grounded answer. UI code may format or display output, but it must not act as a second model-output judge.**

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

### Local AI

NafCMS may provide a browser-local AI assistant backed by Ollama before broader provider abstractions exist.

- Keep the first implementation simple and local: the admin browser talks directly to Ollama, usually at `http://localhost:11434`.
- Store AI settings below the `ai` key only, for example `ai.type`, `ai.url`, `ai.model`, `ai.embedding_model`, and `ai.instructions`. The v0.0.1 local-only assistant has no persisted API-key setting.
- `ai.type=local` means browser-local Ollama. `ai.type=remote` is reserved for a later remote/OpenAI-compatible provider and should not imply a backend abstraction yet.
- `ai.model` is the chat/generation model. `ai.embedding_model` is optional and is used only for semantic tool routing; leave it empty to use the deterministic keyword router.
- The Local AI settings UI should list only Ollama chat models whose `/api/show` capabilities include `tools` for `ai.model`; models without tool support should not be selectable for the assistant because the admin chat relies on tool calls.
- Embedding models return numeric vectors, not chat responses. Local Ollama embeddings should use `/api/embed` and cosine similarity, with keyword routing as a fallback if Ollama or the embedding model is unavailable.
- Prefer live/streaming responses for local Ollama assistant interactions when the UI benefits from immediate feedback.
- Treat `ai.instructions` as installation-specific assistant guidance that is applied in addition to this `AGENTS.md`; it must not replace repository or shipped-project safety rules.
- The assistant should use concrete CMS tools and APIs for CMS actions such as creating drafts, inspecting health, summarizing content, or generating themes. Do not give the model unbounded write access to the filesystem or database.
- Tool and plugin metadata should remain the source of truth for what the assistant can do; do not create a separate manually maintained AI capability list.

### AI Tool Routing

The local assistant must not send the full MCP tool registry to the model once tool counts grow. The backend owns tool selection.

- `LocalAiToolBridge::definitions()` enriches MCP tool definitions through `LocalAiToolCatalog`; do not maintain a parallel static capability list.
- `LocalAiToolBridge::selectDefinitions()` is the stable entry point for the admin assistant and `/api/admin/ai/tools/select`.
- Tool routing lives under `Naf\CMS\Services\Ai\ToolRouting`.
- Always apply deterministic hard filters before ranking: permissions/scopes from MCP identity, write intent, destructive intent, requested mode, requested capabilities, and UI area.
- `KeywordToolRouter` is the reliable fallback router using routing text, parameters, examples, capabilities, mode, and UI context.
- `SemanticToolRouter` is the primary orchestration point. When `ai.embedding_model` is configured it uses `CosineToolRouter` with `OllamaEmbeddingClient`; otherwise it falls back to `KeywordToolRouter`.
- `CosineToolRouter` should batch tool embeddings via `/api/embed` rather than making one Ollama request per tool. If embedding generation fails, fall back to keyword routing instead of failing the assistant.
- `cms_tools_search` is the tool reload path. Prefer exposing it in selected tool sets when there is room so the model can search for additional tools without seeing the whole registry.
- If routing cannot identify a relevant tool, expose `cms_tools_search` instead of returning generic "best available" read tools. Let the model search iteratively rather than forcing a weak match.
- Do not add hard-coded tool-name routing hooks such as "if this phrase then this exact tool" in prompts, JavaScript, or PHP. Tool choice should emerge from tool descriptions, schemas, metadata, semantic routing, and `cms_tools_search`.
- Plugins should register executable tools plus metadata such as description, routing description, examples, capabilities, mode, and scopes. Plugins should not own cross-registry routing or permission decisions.
- Categories such as `cms`, `plugins`, `external`, and `context` remain useful for organization/debugging, but semantic routing should not depend on categories alone.

### AI Admin Actions

The initial local assistant should stay focused on tool-driven CMS assistance, navigation, and stable domain tools.

- Admin actions should be exposed through executable MCP tools and their returned UI Actions. Keep navigation restricted to safe internal `/admin` URLs.
- Article and content write flows should prefer domain MCP tools that already use the relevant Content API/versioning workflow.
- Do not add browser-side direct command aliases that bypass model tool selection. If a UI mode should be available, expose it through a tool/action and improve that tool's description or metadata.

## Capability Concept

If NafCMS later gets a capability registry, the tool or plugin system should remain the source of truth wherever possible.

Not ideal:

- Register a tool once
- Describe the same capability again separately in a registry

Better:

- Tools, plugins, or modules may optionally provide metadata
- `cms_capabilities` can derive an overview from that metadata
- Admin UI, CLI, docs, and AI clients can use the same information

## Auth and Security

NafCMS should have secure defaults.

- Auth is enabled by default.
- Internal or open modes may be configurable, but must be explicit.
- Tokens and API access secrets should not be stored in plaintext.
- File-based auth can be enough for small installations.
- Database-backed auth can be added later.
- Permissions should use clear permission names.
- MCP tools should support scopes and permissions.

## Audit

Audit entries record domain-relevant changes with redaction and actor metadata.

- Prefer `audit()->record(AuditEntry::create(...))` when an explicit audit boundary makes the workflow clearer.
- `AuditEntry::create(...)->record()` is equivalent and remains acceptable for compact domain actions.
- Use `audit_log('action.name', 'subject', $id, $old, $new, $meta)` only for short direct calls where building the entry inline would add noise.
- Keep actor resolution inside `Naf\CMS\Support\AuditActorResolver`; other code should not reach into global auth helpers just to build audit metadata.

## Development Style

When building NafCMS:

- Build simple real workflows first.
- Abstract only after patterns become visible.
- Avoid architecture for its own sake.
- Work in small, testable steps.
- Prefer real admin requirements over theoretical elegance.
- Keep plugin compatibility in mind early.
- Treat AI support as a native extension, not an afterthought.

## Testing

- Run `composer test` for the PHPUnit suite.
- Run `npm run test:builder` for the PageBuilder DOM flow, including nested save/reload, preview, publish payloads, and validation-error state retention.
- Keep fast unit tests as the default safety net.
- Extend `Tests\Support\NafTestCase` for unit tests that need common CMS fixtures such as `mockArticle()`, `mockArticleVersion()`, or Editor.js block payload helpers.
- Use `Tests\Support\DatabaseTestCase` for DB-shaped service or repository tests that can run against an in-memory SQLite database.
- Prefer injecting a `PDO` into query-oriented services for tests, while production code may continue to resolve the configured NAF database connection.
- Do not require Docker or a local MySQL server for the default test path.

## Development Environment Versus Shipped Project

This repository is the NafCMS development environment. It may contain planning notes, local IDE metadata, exploratory docs, and implementation guidance that help build the product.

What gets committed to the actual distributable NafCMS project should be treated as the user-facing product:

- Keep committed files useful for people installing, extending, or auditing NafCMS.
- Avoid shipping local-only assumptions from this workstation.
- Do not require Docker for the primary development path.
- Prefer a bare-metal-first setup using PHP 8.3+, Composer, the built-in PHP server, and local CLI commands.
- Add Docker as a second setup path later, with a ready-to-run `docker compose up -d` experience.
- Keep both setup paths behaviorally equivalent: same migrations, same env names, same CLI commands, same public entry point.
- Do not let Docker-specific paths, hostnames, ports, or service names leak into core application code.

There should eventually be two kinds of project guidance:

- Development guidance for contributors building NafCMS itself.
- Shipped guidance for users and AI agents working inside an installed NafCMS project.

The shipped project should include its own `AGENTS.md` that explains the application structure, extension points, commands, conventions, and safe editing rules from the perspective of someone using NafCMS, not building the NafCMS source distribution.

## CMS API Reference

The public `cms()` entry points and registry-derived reference tables live in the README's Auto Reference section. Keep this file focused on vision, conventions, and safe editing guidance; when public pages, API endpoints, content types, permissions, MCP tools, or examples change, update the source registry and run `vendor/bin/naf docs:sync` from a host that has this package installed.

## CMS Registry Conventions

### Data Access Hierarchy

Choose the highest layer that matches the job. Move downward only when the current layer cannot express the query cleanly.

Layer 1: ContentApi for domain actions that validate payloads, know lifecycle rules, and own side effects.

```php
$result = cms()->articles()->create($payload, $authorId);
cms()->articles()->publish($result['articleId'], 'de');
```

Audits leben ausschließlich in Layer 1 (ContentApi). Storage, Repository und db() auditieren nicht — andernfalls entstehen Doppel-Einträge.

Layer 2: Storage for generic CRUD and Criteria-based reads over one entity.

```php
$media = cms()->media()->storage()->first(
    Criteria::create()->equals('visibility', 'public')->sort('created_at', 'DESC')
);
```

Layer 3: Repository for joins, aggregates, optimized reads, and named query behavior.

```php
$articles = cms()->articles()->repository()
    ->findPublishedWithLatestVersionByCategory($categoryId, 10);
```

Layer 4: `db()` for raw SQL in migrations, diagnostics, and edge cases that do not belong in a reusable repository yet.

```php
$statement = db()->prepare('SELECT COUNT(*) FROM article_versions WHERE status = :status');
$statement->execute(['status' => 'published']);
```

Refactor escalation rule: when a Layer N solution appears twice, or when a `Criteria` query needs more than three filters, move it to Layer N-1.

ContentApis do not have to expose every escalation layer. When the domain has no use for a Repository because Storage plus a small I/O service already covers it (for example `MediaApi`, where file I/O lives in `MediaStorageService`), the path is `ContentApi → Storage → db()` and Layer 3 is simply skipped. Each ContentApi class should document its concrete data access path in its class-level docblock so the available accessors are obvious without reading the whole file.

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
- Article Editor.js block renderers live under `app/Content/Article/Block` and are registered through `Naf\CMS\Content\Article\BlockRegistry`.
- Do not add global CMS block registries for article-specific rendering; expose block extension through the Article content type.
- Pages and API endpoints should reach article form/catalog/rendering behavior through the Article content type instead of directly creating scattered article services.
- Treat articles as the first content type, not as a special HTTP architecture.
- Prefer `cms()->content(Article::class)` for lifecycle operations.
- Keep `cms()->articles()` as a convenience doorway to `cms()->content(Article::class)` unless a repeated cross-type pattern proves a better abstraction.
- Do not add a type alias before its model exists.
- Abstract `ContentApi` only after at least two real content types reveal a shared shape.

### Page Builder Block Types

- Register page-builder block metadata through `cms()->blockTypes()`.
- Core defaults live under `app/Content/Page/Block/Types`; each block type is a small PHP class implementing `BlockTypeInterface`.
- Core block types are registered explicitly in `bootstrap.php` with individual `cms()->blockTypes()->add(new ...BlockType())` calls, following the same path plugins use. Do not reintroduce an `app/blockTypes.php` aggregator or directory scanning for block type registration.
- A block type describes `id()`, `label()`, `regions()`, `defaults()`, and `fields()` for the admin builder.
- A block type may return `unique() === true` when only one instance should exist in a PageBuilder custom layout. The builder must enforce uniqueness from this metadata instead of hard-coding individual block type names.
- Plugins may call `cms()->blockTypes()->add(new MyPluginBlockType())` during bootstrap.
- Keep block type metadata separate from rendering. `cms()->blockTypes()` tells the builder what can be edited; `cms()->blocks()` maps a saved type to a `.phtml` view.

### Block View Registry

- Register page-builder block view mappings through `cms()->blocks()`.
- Core defaults live in `Naf\CMS\Content\BlockViewRegistry`; plugins may call `cms()->blocks()->add('mycustom', 'page.blocks.mycustom')` during bootstrap.
- Keep aliases explicit in the registry constructor so agents can grep which block types share a view.
- Unknown block types must fall back to `page.blocks.placeholder`.
- Block views live under `src/views/page/blocks`; files directly inside that directory should be renderable blocks, while shared helpers belong in `src/views/page/blocks/_helpers`.

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

NafCMS should be easy for AI coding agents to inspect without needing tribal knowledge.

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

Every shipped NafCMS app should make the common AI inspection path obvious:

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

The `AGENTS.md` shipped with generated or installed NafCMS projects should be concise, practical, and oriented around safe modification.

It should explain:

- What NafCMS is and which parts are core versus plugins.
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

NafCMS should become a complete product built on NAF and powered by its plugin ecosystem.

It is itself a NAF plugin and is always installed into a host application; this repository ships no front controller and is never served directly. `naf/app` is the starter host for that.

It should feel:

- Lean like a custom framework project
- Extensible like a plugin system
- Productive like a modern CMS
- AI-ready from day one

Working name: **NafCMS**.

## Researched NAF Foundation

NafCMS should start from the official NAF package ecosystem instead of copying a full-stack CMS architecture from elsewhere.

Current research sources:

- GitHub/Packagist source package: `naf/framework` (`https://github.com/nafphp/framework`, `https://packagist.org/packages/naf/framework`)
- NAF package overview: `https://packagist.org/packages/naf/`
- Individual package sources use the `https://github.com/nafphp/<package>` pattern where published, for example `naf/database`, `naf/view`, and `naf/i18n`.

NAF core provides routing, dispatching, PSR-oriented infrastructure, plugin loading, configuration merging, view path registration, and bootstrap hooks. Everything beyond the smallest HTTP core should remain optional through Composer packages or NafCMS modules/plugins.

Recommended initial Composer base for NafCMS:

- `naf/framework`: required foundation.
- `naf/view`: required for the admin UI and server-rendered CMS screens.
- `naf/database`: required once content, users, sessions, or workflows are persisted.
- `naf/orm`: useful for the first CMS entities and repositories, especially content, users, roles, and permissions.
- `naf/session`: required for admin authentication and account workflows.
- `naf/form`: useful for form memory, validation-adjacent workflows, and admin forms.
- `naf/i18n`: required early because NafCMS should support localized content and a multilingual admin UI.
- `naf/cli`: required for migrations, user creation, maintenance commands, and operational tooling.
- `naf/queue`: required for translation, AI, media processing, publication tasks, and async maintenance.
- `naf/schedule`: required for scheduled publishing, recurring maintenance, and queue ticker workflows.
- `naf/mail`: useful for invites, password reset, workflow notifications, and publication/review notifications.
- `naf/client`: useful for external APIs, AI providers, webhooks, and integration plugins.
- `naf/mcp`: used successfully in WeOnlyWalk for concrete AI/MCP tools; keep it in scope for NafCMS even if its public package metadata is less visible than the other packages.

Recommended dependency phases:

1. Bootstrap phase: `framework`, `view`, `form`.
2. CMS core phase: `database`, `orm`, `session`, `i18n`, `cli`.
3. Operational phase: `queue`, `schedule`, `mail`.
4. AI/integration phase: `client`, `mcp`.

## NAF Plugin Rules For NafCMS

NAF plugins are Composer packages with package type `naf-plugin`. A plugin can provide a `bootstrap.php`, app configuration, views, page/API registrations, services, commands, tools, and other app-like files.

For NafCMS, preserve that model:

- A NafCMS feature should be a plugin or clearly isolated module unless it is truly shared CMS core.
- Plugins should register capabilities through executable code, not duplicated static registries.
- Plugin configuration should merge cleanly into app config.
- Plugin views should be added to the view search path rather than copied into the app.
- Plugin bootstrap code may register pages, API endpoints, commands, events, scheduler entries, queue jobs, MCP tools, admin navigation, and permissions.
- NafCMS-specific plugin metadata may be added later, but executable plugin/tool definitions should remain the source of truth wherever possible.
- Feature registration should be explicit. Do not activate pages, API endpoints, block types, content types, tools, commands, jobs, migrations, or permissions merely because a file exists in a directory. Filesystem scans are acceptable for resources and diagnostics, such as language packs, queue files, view integrity checks, or example smoke tests, but not as a hidden feature registration mechanism.

## WeOnlyWalk As Reference Material

The project `/Users/flo/PhpStormProjects/weonlywalk` is the main reference implementation. It should be mined for proven patterns, not copied wholesale.

Useful WeOnlyWalk elements:

- Composer stack: uses `naf/framework`, `form`, `database`, `view`, `i18n`, `orm`, `session`, `cli`, `schedule`, `mail`, and `mcp`.
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
- Frontend build pattern: Tailwind CSS plus semantic CSS in `app/app/Resources/css/naf.css`; built output in `app/public/css/naf.css`.
- Editor pattern: Editor.js-based content JSON is useful as a starting point for article content blocks.
- MCP pattern: tools are small PHP classes implementing `ToolInterface`; scope-aware tools implement `ScopedToolInterface`; schemas are defined with `Naf\MCP\Support\Schema`.
- Existing MCP ideas: `article_status_counts`, `system_health`, and `queue_status` are directly relevant to NafCMS.
- Operational learning: scheduled jobs may enqueue work while queue workers execute it; health views and MCP tools should expose pending/deadletter job counts.

Avoid carrying over WeOnlyWalk-specific features into NafCMS core:

- YouTube publishing and channel statistics.
- WeOnlyWalk-specific prompt files and generated YouTube metadata rules.
- Route/location-specific web research logic.
- Project branding, copy, and editorial assumptions.

These can later become example plugins or separate integration plugins.

## Suggested NafCMS Core Modules

Start with small modules that match real admin needs:

- `Naf\CMS\Admin`: dashboard, navigation, layout, health/status panels.
- `Naf\CMS\Auth`: login, logout, sessions, password hashing, invites, explicit auth modes.
- `Naf\CMS\Users`: users, roles, permissions, account management.
- `Naf\CMS\Content`: articles, versions, statuses, preview, publish, schedule, archive.
- `Naf\CMS\Taxonomy`: categories and tags.
- `Naf\CMS\Localization`: languages, translation helpers, language switching.
- `Naf\CMS\Media`: uploads, media metadata, image handling.
- `Naf\CMS\Jobs`: queue and scheduler integration.
- `Naf\CMS\Mcp`: scope-aware tools exposing concrete CMS operations.
- `Naf\CMS\Plugins`: admin navigation, permission, route, migration, command, job, scheduler, asset, and MCP registration points.

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
- `jobs` or file-backed queue storage, depending on the selected NAF queue driver.

Prefer migrations over schema dumps. Keep first migrations readable and reversible. Every new migration must implement a real `down()` rollback; legacy migrations may throw `RuntimeException('manual rollback required')` only when reversal would be unsafe.

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
- `cms_plugin_overview`: loaded NAF/NafCMS plugins and their declared admin/capability metadata.
- `cms_user_role_summary`: role and permission counts, optionally scoped to admin-readable users.

Do not maintain a separate static capability list unless it is derived from tool/plugin metadata.

## Admin UI Direction From WeOnlyWalk

The NafCMS admin should use the dense enterprise direction already proven in WeOnlyWalk:

- Server-rendered `.phtml` views first.
- Calm layout with persistent admin navigation.
- Tables for resources, filters, search, status chips, and bulk-friendly actions.
- Forms and modals for common actions.
- Semantic CSS for reusable admin structures; Tailwind utilities for local one-off layout details.
- Dark mode support through a root `dark` class.
- Build assets from `app/app/Resources` into `app/public`; do not manually edit build output.

Avoid landing-page or marketing-style admin screens. The first screen should be useful as an operational dashboard.

For this NafCMS repository, browser-consumed assets must exist below `public/css` and `public/js`. `app/Resources` is for source/build inputs only.

Admin UI components should be self-contained by default. Prefer Tailwind utility classes and small local class decisions inside `src/views/components/admin/*`; keep global CSS for Tailwind entrypoint concerns, tokens, base behavior, complex selectors, and third-party integration only. A plugin should usually be able to override one component file without also patching global CSS.

## Operational Notes To Carry Forward

- Treat queue deadletter state as first-class observability.
- Long-lived workers can hold stale database connections; support worker lifecycle limits such as max jobs and max runtime.
- Scheduled tasks should be visible separately from queued jobs when possible.
- Health checks should cover database connectivity, storage writability, loaded plugins, session backend, queue state, and MCP token/auth status.
- Auth should be on by default; open/internal modes must be explicit.
- Tokens and external API credentials must not be stored in plaintext.

## First Build Target

The first useful NafCMS milestone should be:

1. NAF app skeleton with admin layout.
2. Database migrations for users, roles, permissions, sessions, articles, article versions, categories, tags, and pivots.
3. Login/logout plus a CLI command to create the first admin user.
4. Admin dashboard with health, content status counts, queue status, and loaded plugin overview.
5. Article list, create/edit draft, preview, review, publish, schedule, archive.
6. Language-aware article versions.
7. Three MCP tools: system health, queue status, article status counts.
