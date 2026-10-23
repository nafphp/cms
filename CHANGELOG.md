# Changelog

All notable changes to NafCMS are documented here. This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses semantic version tags, while APIs and stored preview payloads may still change before `0.1.0`.

## [Unreleased]

### Added

- Content events and `CmsEvent` constants for page, article, form, media and navigation writes, including persisted form submissions and a listener example.
- Conventional block views through `ProvidesBlockViewInterface`, with explicit mapping precedence, missing-view warnings and registry removal methods.
- Skip-to-content links in admin, authentication and public layouts, with focusable main-content targets in all shipped page templates.
- Host-aware `integrity:check` with view resolution, slot agreement, content-anchor and block-view checks; orphan checks remain package-only.
- `make:block`, `make:template` and `make:plugin` commands with explicit registration instructions and protection against existing targets.
- `@api` annotations and documentation for the stable extension surface.
- Keyboard slot movement and polite add/remove/reorder announcements in the page builder.
- Automated WCAG A/AA checks using axe-core, shared builder DOM fixtures and layout accessibility regression tests.

- F9 switches the admin and login screens between dark and light. The choice is stored as before and follows into other open tabs; the toggle button names the shortcut. The handler cancels F9, so Edge's Immersive Reader and Firefox's Reader View do not open on these screens.
- `cms()->pageTemplates()` registry for page-builder templates. Plugins can add, replace, or remove templates; the host's `src/Resources/pages/page_templates.php` is read last and wins by id. `Layout::fromDefinition()` rebuilds a layout from a definition array.

### Fixed

- The Studio host loads `src/services.php` before its custom routes.
- Example templates provide their own published host CSS instead of relying on utilities outside the package Tailwind source scan.
- Toasts use a persistent polite live region and no dialog roles on individual messages.
- Modal notifyJs dialogs trap Tab focus, focus the alert confirmation button and restore the opening element after dismissal.
- Admin and authentication boot scripts respect the system color scheme; later system changes apply while no preference is saved.
- Admin controls that removed their outlines now expose a visible keyboard-focus ring.
- Builder and remaining selected view strings, accessible labels and notifications use translated English keys; remove the unused role prompt.

- The page builder now offers the host's and plugins' templates. It previously read only the package's template file, while saving and rendering already included the host file, so host templates rendered but could not be selected.

### Changed

- Require `naf/form` 0.2.4, which validates CSRF tokens for every method except GET, HEAD and OPTIONS and no longer exempts Bearer requests, and `naf/mcp` 0.2.6, which exempts its own RPC route from that check.
- `PageTemplateCatalog` is deprecated in favour of `cms()->pageTemplates()`.

## [0.0.2] - 2026-10-14

### Fixed

- Include the package's top-level PHP templates when building Tailwind CSS. The previous source path pointed at a nonexistent directory, so newly used template-only utilities were omitted.
- Verify template-only utility generation and exclusion of unrelated local files through the actual Tailwind CLI in CI.

## [0.0.1] - 2026-10-14

### Changed

- Apply the shared NAF PHP code style to source, tests, examples and templates, with Composer commands and a CI check.
- Require Framework 0.2.9 for CLI rendering guards, CLI 0.2.2 for Composer binary bootstrap paths, and explicitly declare the CMS's PDO dependency.

### Fixed

- Standalone page charts now call the host's global translation fallback when NAF's translator is not loaded; the previous import targeted a nonexistent namespaced function.
- Keep static analysis free of application boot side effects and remove an environment-dependent baseline entry.
- Build Tailwind from explicit package sources and verify both starter-owned and CMS-managed guides in the fresh-host smoke test.
- Replace the obsolete local Auth path-repository instructions with the published dependency setup.
- Update the build-only source-map-js dependency to the patched 1.2.2 release and keep MCP fixtures compatible with published input validation.

### Added

- Installable `naf-plugin` package with explicit pages, APIs, content types, block types, commands, migrations, views, events, permissions, and MCP tools.
- Admin workflows for articles and versions, pages/Sitebuilder, forms, media, navigation, themes, settings, users, roles, permissions, audit, queue, and health visibility.
- Local Ollama assistant with backend-selected, scope-aware MCP tools, keyword/optional embedding routing, and tool-owned metadata.
- Host-safe asset publishing, immutable package defaults, atomic runtime overrides, and a managed host `AGENTS.md` installer.
- PHP 8.3/8.4 CI, MariaDB and SQLite migration coverage, DOM builder flow, package archive checks, and a fresh mirrored-host installation smoke test.

### Security

- Central stored-output sanitizing and URL policy, SSRF-resistant outbound HTTP, auth-by-default, CSRF-protected logout, login/form rate limits, request limits, password policy, and baseline security headers.
- Repository-level RBAC privilege boundaries for admin/role assignment and dedicated permissions for AI instructions and editable theme code.
- No persisted remote AI key field; routing fallback telemetry omits normal user query text.

### Known Limitations

- The Sitebuilder and its version-1 payload are experimental before `0.1.0`; undo/redo, collaborative editing, full responsive authoring, and backward payload migrations are not promised.
- Local AI supports browser-local Ollama only. Remote providers and a corresponding secret store are reserved for a later release.
- Theme JavaScript is disabled; reviewed plugins may register scripts through normal PHP/bootstrap paths.
- PHP 8.5 is an experimental allowed-failure CI target while the current ORM dependency emits deprecations.

[Unreleased]: https://github.com/nafphp/cms/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/nafphp/cms/releases/tag/v0.0.1
