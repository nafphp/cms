# Changelog

All notable changes to NafCMS are documented here. This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses semantic version tags, while APIs and stored preview payloads may still change before `0.1.0`.

## [Unreleased]

### Changed

- Apply the shared NAF PHP code style to source, tests, examples and templates, with Composer commands and a CI check.
- Require Framework 0.2.9 for CLI rendering guards, CLI 0.2.2 for Composer binary bootstrap paths, and explicitly declare the CMS's PDO dependency.

### Fixed

- Standalone page charts now call the host's global translation fallback when NAF's translator is not loaded; the previous import targeted a nonexistent namespaced function.
- Keep static analysis free of application boot side effects and remove an environment-dependent baseline entry.
- Build Tailwind from explicit package sources and verify both starter-owned and CMS-managed guides in the fresh-host smoke test.
- Update the build-only source-map-js dependency to the patched 1.2.2 release and keep MCP fixtures compatible with published input validation.

## [0.0.1] - 2026-07-21

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
