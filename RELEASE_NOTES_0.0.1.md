# NixCMS v0.0.1 Release Notes

NixCMS v0.0.1 is the first preview of a lightweight CMS distributed as a NixPHP plugin. It provides real content/admin workflows, explicit plugin extension points, server-rendered views, a permission- and scope-aware MCP tool layer, and an experimental Sitebuilder.

## Highlights

- Composer package type `nixphp-plugin`, installed into an ordinary `nixphp/app` host.
- Articles with localized versions and workflow states; pages, forms, media, navigation, themes, users/RBAC, audit, queue, and health administration.
- Explicit Page/API/content/block registries and a complete example plugin covering a view, migration, and self-describing scoped MCP tool.
- Local Ollama assistant whose available actions come from executable tool/plugin metadata rather than a duplicated capability list.
- Host runtime overrides and published assets that never require normal writes below `vendor/nixphp/cms`.

## Security Baseline

Auth is enabled by default. The release includes CSRF and repository-level privilege boundaries, XSS/URL policies, SSRF-resistant HTTP, rate and payload limits, password rules, scoped MCP tokens, security headers, atomic runtime writes, and a private vulnerability-reporting policy. Review host TLS, proxy trust, filesystem permissions, database credentials, backups, and token scopes before production use.

## Preview Boundaries and Upgrades

Sitebuilder payloads, extension APIs, and storage shapes may change before v0.1. The builder has explicit size/depth constraints and no compatibility guarantee for old experimental payloads. Local AI is Ollama-only, PHP 8.5 remains experimental, and custom theme JavaScript is disabled. Read `CHANGELOG.md` before every upgrade, back up the database and `storage/nixcms`, run Composer, publish/check assets, apply migrations, and exercise the installation smoke path.

## Verified Commands

```bash
composer validate --strict
composer test
composer audit --locked
npm ci
npm run test:builder
npm run build
vendor/bin/nix docs:check
vendor/bin/nix integrity:check
scripts/check-package.sh
scripts/package-smoke.sh
```

The `v0.0.1` tag must be created only from the exact clean release commit for which every command above and the GitHub Actions workflow passed.
