# Auth migration

NafCMS delegates generic authentication and authorization to `naf/auth`.

- `auth()->authenticate(new PasswordCredentials($username, $password), 'database')` (with `Naf\Auth\Credentials\PasswordCredentials`) authenticates against the CMS database provider.
- `auth()->user()` returns the CMS `UserIdentity`; `auth()->can()` and `auth()->hasRole()` evaluate its permissions and roles.
- Existing `user()->get()`, `user()->isLoggedIn()`, `Page::requirePermission()`, `ApiEndpoint::requirePermission()` and ANY-permission declarations retain their CMS API.
- Page/API authorization, navigation visibility and the admin guard use `ResourceAuthorization` to apply CMS requirements through the auth grant checks.
- The existing password service, rate limiter, enums, ORM models, repositories, invitations and UserPrivilegePolicy stay in the CMS.

The integration uses `Provider\ProviderInterface`, `Identity\IdentityInterface` and `Session\StateStoreInterface` from `Naf\Auth`. See the [authentication guide](https://nafphp.github.io/docs/auth/) for the current API.

## Published dependencies

The CMS requires the published `naf/auth` 0.2 series through its normal Composer
manifest. Install or update the CMS in the host without sibling path repositories
or local version aliases:

```bash
composer require naf/cms --with-all-dependencies
```

For a CMS contributor checkout, `composer install` uses the committed lock of
published packages. The old `composer.local.json` and `composer.local.lock` files
are no longer needed; stop setting the `COMPOSER` override before running the
normal checks.

## Host integration

List session, auth, then CMS in the host's `app/plugins.php`/`src/plugins.php`. CMS boots auth before its own provider registration if needed. The session service must be registered before Auth is first resolved.

CMS binds `CmsSessionStateStore` to `Naf\Auth\Session\StateStoreInterface` before resolving the auth manager. Hosts overriding the manager or its store must preserve that CMS adapter (or supply an equivalent) to retain the account session-management screens and session revocation metadata.

The CMS database provider returns `UserIdentity`, a wrapper around its ORM User. Other providers that should support existing CMS `user()->get()` consumers must map to that CMS identity. The generic auth package itself has no such restriction.

## Existing sessions

Database authentication stores a SHA-256 fingerprint of the password hash alongside the generic `auth` record. Restoration compares it with the account's current credentials. A password change or reset therefore invalidates previously issued sessions with both PHP file storage and the database handler. An old request writing its session after the reset cannot restore access.

Sessions without this credential binding, including legacy `userId` sessions, require a fresh login after upgrading. Do not infer a fingerprint for an existing session: that would adopt credentials the session never verified. Deleted users and malformed records become guests, with the generic record and legacy metadata removed.

New logins keep `userId` as session-table indexing metadata. Authentication and normal identity lookup use the auth manager. Logout clears both layers and retains the CMS's existing whole-session destruction behavior.

The database provider passes the verified credential fingerprint to the shared CMS state store before publishing a login. A reset between verification and publication is rejected. Password rehashing uses a conditional update against the hash that was verified, so an automatic upgrade cannot overwrite a concurrent reset. Rehashing changes the credential fingerprint and requires other sessions to authenticate again.

## HTTP behavior

- Anonymous HTML page access keeps the login redirect and remembered destination.
- Authenticated HTML denials render the existing 403 page.
- API denials are JSON: 401 for missing authentication, 403 for insufficient permission. These intentionally replace the previous HTML redirects from API routes.
- CMS adapters preserve `AuthException`, including its generic cause and 401/403 code.
- The existing form/CSRF middleware and POST-only logout route remain in place.
- Login destinations reject backslashes and control characters and use the existing internal URL policy. Absolute remembered URLs contribute only their local path and query.

## Reviewer attribution

Article review now requires a real CMS actor and attributes the review to that actor's User ID. It no longer falls back to the first database user when a session ID is missing.

## Remaining boundaries

`UserPrivilegePolicy` deliberately retains its connection-bound business checks. MCP identities also retain their separate token scopes and wildcard rules; mapping those to user permissions requires an explicit application decision. No other NAF package API was changed.

The generic manager is scoped to one request/job. Long-running hosts must reset/recreate it between executions. Existing `user()` test overrides are compatibility fixtures, not an authentication API for request data.
