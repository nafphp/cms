# Auth migration

NafCMS delegates generic authentication and authorization to `naf/auth`.

- `auth()->authenticate(new PasswordCredentials($username, $password), 'database')` (with `Naf\Auth\Credentials\PasswordCredentials`) authenticates against the CMS database provider.
- `auth()->user()` returns the CMS `UserIdentity`; `auth()->can()` and `auth()->hasRole()` evaluate its permissions and roles.
- Existing `user()->get()`, `user()->isLoggedIn()`, `Page::requirePermission()`, `ApiEndpoint::requirePermission()` and ANY-permission declarations retain their CMS API.
- Page/API authorization, navigation visibility and the admin guard use `ResourceAuthorization` to apply CMS requirements through the auth grant checks.
- The existing password service, rate limiter, enums, ORM models, repositories, invitations and UserPrivilegePolicy stay in the CMS.

The integration uses `Provider\ProviderInterface`, `Identity\IdentityInterface` and `Session\StateStoreInterface` from `Naf\Auth`. See the auth package README for the current API.

## Local development before the auth release

The sibling checkout is `../naf/auth`. The ignored `composer.local.json` is the CMS manifest plus a path repository. From the CMS checkout:

```bash
php -r '$package = json_decode(file_get_contents("composer.json"), true, flags: JSON_THROW_ON_ERROR); $package["repositories"] = [["type" => "path", "url" => "../naf/auth", "options" => ["symlink" => true, "versions" => ["naf/auth" => "0.1.0"]]]]; file_put_contents("composer.local.json", json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);'
cp composer.lock composer.local.lock
COMPOSER=composer.local.json composer update naf/auth --minimal-changes
composer test
composer analyse
npm run test:builder
```

The version in the path repository is a local development alias, not a published tag. Composer symlinks the auth source; the migration does not upgrade unrelated CMS dependencies. Subsequent local installs should use:

```bash
COMPOSER=composer.local.json composer install
```

The tracked CMS `composer.lock` intentionally remains the pre-release lock. A normal install using that lock cannot satisfy the newly added auth requirement until it is refreshed. Once a real `naf/auth` 0.1 release exists, run `composer update naf/auth --minimal-changes` against the normal manifest, commit that release lock, and stop using the local manifest. Do not publish local path locks or invent a release reference.

## Host integration

List session, auth, then CMS in the host's `app/plugins.php`/`src/plugins.php`. CMS boots auth before its own provider registration if needed. The session service must be registered before Auth is first resolved.

CMS binds `CmsSessionStateStore` to `Naf\Auth\Session\StateStoreInterface` before resolving the auth manager. Hosts overriding the manager or its store must preserve that CMS adapter (or supply an equivalent) to retain the account session-management screens and session revocation metadata.

The CMS database provider returns `UserIdentity`, a wrapper around its ORM User. Other providers that should support existing CMS `user()->get()` consumers must map to that CMS identity. The generic auth package itself has no such restriction.

## Existing sessions

Old `userId` sessions are restored through the database provider and acquire an `auth` record containing only provider name and string identifier. Deleted users and malformed new records become guests, with both the generic record and legacy metadata removed.

New logins keep `userId` as session-table indexing metadata. Authentication and normal identity lookup use the auth manager. Logout clears both layers and retains the CMS's existing whole-session destruction behavior.

## HTTP behavior

- Anonymous HTML page access keeps the login redirect and remembered destination.
- Authenticated HTML denials render the existing 403 page.
- API denials are JSON: 401 for missing authentication, 403 for insufficient permission. These intentionally replace the previous HTML redirects from API routes.
- CMS adapters preserve `AuthException`, including its generic cause and 401/403 code.
- The existing form/CSRF middleware and POST-only logout route remain in place.

## Reviewer attribution

Article review now requires a real CMS actor and attributes the review to that actor's User ID. It no longer falls back to the first database user when a session ID is missing.

## Remaining boundaries

`UserPrivilegePolicy` deliberately retains its connection-bound business checks. MCP identities also retain their separate token scopes and wildcard rules; mapping those to user permissions requires an explicit application decision. No other NAF package API was changed.

The generic manager is scoped to one request/job. Long-running hosts must reset/recreate it between executions. Existing `user()` test overrides are compatibility fixtures, not an authentication API for request data.
