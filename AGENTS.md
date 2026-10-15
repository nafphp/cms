# Working on naf/cms

NAF is a small PHP framework with optional Composer plugins. Its core owns boot,
configuration, the service container, routing, events and PSR-7 responses. Prefer existing
NAF helpers, services and extension interfaces; keep application business rules in the host.
This package declares `type: naf-plugin` and is discovered after installation in a NAF host.
The plugin repository itself is not the application's web root.

Before changing code, read the [shared contribution workflow](https://github.com/nafphp/docs/blob/main/AGENT_WORKFLOW.md)
and [release procedure](https://github.com/nafphp/docs/blob/main/RELEASING.md).
In the multi-repository workspace, the same documents are in the sibling `docs/` checkout;
use the linked copies when working from a standalone clone. Preserve other contributors' work.
Review and update user documentation with every behavior change. Source fixes use an RC branch;
verified documentation-only changes can be merged and published by the agent.

## Start here

NafCMS is a CMS plugin installed into a NAF host; this repository has no front controller
and must not be served directly. Read [README.md](README.md), [composer.json](composer.json),
[bootstrap.php](bootstrap.php) and [src/functions.php](src/functions.php) before changing it.
Install into a configured host with `composer require naf/cms` and follow its setup/migrations.
Read [CMS_GUIDE.md](CMS_GUIDE.md) completely before modifying CMS behavior; it preserves the
existing domain, security, UI, data-access and testing rules. Read `examples/AGENTS.md` as well
when working on examples.

Use `Naf\CMS\cms()` and its page/API/content registries. Extend Content APIs for domain actions,
Storage/repositories for data, and existing service overrides for integrations. The working
[extension examples](examples/) cover pages/permissions, content types, blocks, MCP tools,
complete plugins and service replacement; read `examples/AGENTS.md` before editing them.
Prefer those registration paths over adding a second router, auth layer or persistence stack.

The actual Composer checks are `composer test` (`tests/run.php`), `composer analyse` and
`composer validate --strict`; `composer check` runs analysis and tests. For PageBuilder changes
also run `npm run test:builder`. For asset/source-discovery changes, run `npm run test:assets`
and rebuild with `npm run build`. Follow the relevant testing guidance in `CMS_GUIDE.md`.
Keep registry-derived reference material current with `vendor/bin/naf docs:sync` from an installed host when public
CMS registrations change. The detailed guide remains authoritative for the CMS-specific conventions.

Follow the shared [PHP code style](https://github.com/nafphp/docs/blob/main/CODE_STYLE.md)
and `.php-cs-fixer.dist.php`. Run `composer style:check`; `composer style:fix` applies the rules.
Keep logical steps and local names readable, preserving public signatures and template output.

PHPStan checks `src/` and `tests/` at level 6 without a baseline. Keep collection element
types and EntityStorage's entity generic accurate; do not replace a confirmed diagnostic
with an ignore or a fabricated inline type. See [quality checks](docs/quality-checks.md) for
the analysis scope, symbol discovery and HTTP/CLI regression checks.
