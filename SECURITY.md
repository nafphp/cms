# Security Policy

## Supported Versions

| Version | Security fixes |
| --- | --- |
| `0.0.x` | Supported until a newer documented release line replaces it |
| Unreleased source snapshots | Best effort; reproduce against the latest supported tag |

## Report a Vulnerability Privately

Do not open a public issue for a suspected vulnerability. Prefer GitHub's private security-advisory form at `https://github.com/nafphp/cms/security/advisories/new`. If that form is unavailable while the repository is private or being prepared for release, email `office@florianknapp.de` with the subject `NafCMS security report`.

Include the affected NafCMS and PHP versions, installation mode, required permissions/scopes, a minimal reproduction, observed impact, and any suggested mitigation. Remove real credentials, tokens, personal data, and production database contents. Encrypted details or a safer transfer method can be coordinated in the initial message.

The maintainer will acknowledge receipt, validate and classify the report, coordinate a fix and release window, and credit the reporter when requested. Public disclosure should wait until users have a reasonable opportunity to update. If a report is not security-sensitive, it may be redirected to the public issue tracker with the reporter's agreement.

The security boundary includes auth and RBAC, MCP scopes/tokens, stored CMS output, uploads, outbound HTTP/SSRF controls, package/runtime separation, and dependency vulnerabilities. Unsupported custom plugins, host server configuration, and secrets already exposed outside NafCMS may still need separate remediation by the installation owner.
