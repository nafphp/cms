# v0.0.1 Release Checklist

- [ ] Confirm PHP 8.3 and 8.4, assets, audit, package-smoke, and MariaDB jobs are green on the release commit; record the workflow URL here: `pending`.
- [ ] Run every command in `RELEASE_NOTES_0.0.1.md` locally, including the README-only fresh-host installation.
- [ ] Confirm `composer archive` contains `LICENSE`, `README.md`, `SECURITY.md`, `CHANGELOG.md`, release notes, package code/views/assets, and no `.env`, runtime data, prompts, logs, IDE metadata, tests, caches, or `vendor/`.
- [ ] Review Composer name/type/license/homepage/support, PHP support statement, dependency audit, and Packagist description.
- [ ] Confirm production guidance, Sitebuilder/AI preview limits, security contact, and breaking-change warning are accurate.
- [ ] Confirm no unrelated or user-owned local changes are included in the release commit.
- [ ] Create annotated tag `v0.0.1` on the verified clean commit and verify `git rev-parse v0.0.1^{commit}` equals the release commit.
- [ ] Push the commit and tag only after explicit maintainer approval; publish the matching GitHub/Packagist release notes.
