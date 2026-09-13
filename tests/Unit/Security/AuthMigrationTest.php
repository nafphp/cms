<?php

declare(strict_types=1);
namespace Tests\Unit\Security;

use Naf\Auth\Credentials\PasswordCredentials;
use Naf\CMS\Api\{ApiAuthorization, ApiEndpoint};
use Naf\CMS\Contracts\RateLimiterInterface;
use Naf\CMS\Enums\{Permission, Role};
use Naf\CMS\Events\AuthRedirectEvent;
use Naf\CMS\Exceptions\AuthException;
use Naf\CMS\Handlers\Api\Article\ReviewArticle;
use Naf\CMS\Handlers\Page\Auth\LoginUser;
use Naf\CMS\Page\{Page, PageAuthorization};
use Naf\CMS\Services\Security\{CmsSessionStateStore, CredentialService, DatabaseProvider, RateLimitDecision};
use Naf\CMS\Support\UserHelper;
use Naf\Session\Core\Session;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunTestsInSeparateProcesses};
use Tests\Support\{DatabaseTestCase, JsonRequest};
use function Naf\app;
use function Naf\Auth\auth;
use function Naf\CMS\user;
use function Naf\Session\session;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AuthMigrationTest extends DatabaseTestCase
{
    private MigrationRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $path = BASE_PATH . '/storage/cache/auth-test-sessions';
        if (!is_dir($path)) { mkdir($path, 0700, true); }
        session_save_path($path);
        session()->start();
        $_SESSION = [];
        $this->createSchema(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, password TEXT)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE user_role (user_id INTEGER, role_id INTEGER)',
            'CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER)',
        );
        $this->insertRow('users', ['id' => 7, 'username' => 'alice', 'password' => (new CredentialService())->hashPassword('correct-password')]);
        $this->insertRow('roles', ['id' => 1, 'name' => 'admin']);
        $this->insertRow('permissions', ['id' => 1, 'name' => Permission::PAGES_EDIT->value]);
        $this->insertRow('user_role', ['user_id' => 7, 'role_id' => 1]);
        $this->insertRow('role_permissions', ['role_id' => 1, 'permission_id' => 1]);
        // Exercise the production bootstrap's Auth binding and database provider.
        user(reset: true);
        $this->limiter = new MigrationRateLimiter();
    }

    protected function tearDown(): void
    {
        auth()->logout();
        user(reset: true);
        if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
        parent::tearDown();
    }

    public function testLoginHandlerPublishesActorRotatesSessionAndKeepsMetadata(): void
    {
        JsonRequest::bind(['username' => 'alice', 'password' => 'correct-password'], '/admin/login');
        $before = session_id();
        $response = (new LoginUser($this->limiter))();
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin', $response->getHeaderLine('Location'));
        self::assertNotSame($before, session_id());
        self::assertSame(['provider' => 'database', 'identifier' => '7'], session()->get('auth'));
        self::assertSame(7, session()->get('userId'));
        self::assertSame('7', auth()->user()->getIdentifier());
        self::assertSame(7, user()->get()->getId());
        self::assertTrue(auth()->can(Permission::PAGES_EDIT));
        self::assertTrue(user()->can(Permission::PAGES_EDIT));
        self::assertTrue(user()->hasRole(Role::ADMIN));
        self::assertSame(1, $this->limiter->successes);
    }

    public function testLoginSurvivesReadingThePersistedSessionAgain(): void
    {
        self::assertTrue(auth()->authenticate(new PasswordCredentials('alice', 'correct-password'), 'database'));
        $identifier = session_id();
        session_write_close();
        $_SESSION = [];
        auth()->reset();

        session_id($identifier);
        session()->start();

        self::assertTrue(auth()->check());
        self::assertSame('7', auth()->id());
        self::assertSame(7, user()->get()->getId());
        self::assertTrue(auth()->can(Permission::PAGES_EDIT));
        self::assertSame(7, session()->get('userId'));
    }

    public function testDeletedUserCannotRestoreAnAuthenticatedSession(): void
    {
        self::assertTrue(auth()->authenticate(new PasswordCredentials('alice', 'correct-password'), 'database'));
        $this->pdo->exec('DELETE FROM users WHERE id = 7');
        auth()->reset();

        self::assertFalse(auth()->check());
        self::assertNull(user());
        self::assertNull(session()->get('auth'));
        self::assertNull(session()->get('userId'));
    }

    public function testUnknownProviderCannotUseLegacySessionAsFallback(): void
    {
        session()->set('auth', ['provider' => 'removed-provider', 'identifier' => '7']);
        session()->set('userId', 7);

        self::assertFalse(auth()->check());
        self::assertNull(session()->get('auth'));
        self::assertNull(session()->get('userId'));
        auth()->reset();
        self::assertFalse(auth()->check());
    }

    public function testBadPasswordAndUnknownUserRemainGenericFailures(): void
    {
        foreach (['alice', 'unknown'] as $username) {
            JsonRequest::bind(['username' => $username, 'password' => 'wrong'], '/admin/login');
            $response = (new LoginUser($this->limiter))();
            self::assertStringContainsString('Invalid username or password.', (string) $response->getBody());
            self::assertFalse(auth()->check());
            self::assertNull(session()->get('auth'));
        }
        self::assertSame(2, $this->limiter->failures);
        self::assertSame(0, $this->limiter->successes);
    }

    public function testRateLimitStopsAuthenticationBeforeCredentialLookup(): void
    {
        $this->pdo->exec('DROP TABLE users');
        $this->limiter->allowed = false;
        JsonRequest::bind(['username' => 'alice', 'password' => 'correct-password'], '/admin/login');
        $response = (new LoginUser($this->limiter))();
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('30', $response->getHeaderLine('Retry-After'));
        self::assertFalse(auth()->check());
    }

    public function testPasswordUpgradeStillOccursOnlyAfterVerification(): void
    {
        $old = password_hash('correct-password', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->pdo->prepare('UPDATE users SET password = ? WHERE id = 7')->execute([$old]);
        self::assertFalse(auth()->authenticate(new PasswordCredentials('alice', 'wrong'), 'database'));
        self::assertSame($old, $this->pdo->query('SELECT password FROM users')->fetchColumn());
        self::assertTrue(auth()->authenticate(new PasswordCredentials('alice', 'correct-password'), 'database'));
        $new = $this->pdo->query('SELECT password FROM users')->fetchColumn();
        self::assertNotSame($old, $new);
        self::assertTrue(password_verify('correct-password', $new));
        self::assertSame('argon2id', password_get_info($new)['algoName']);
    }

    public function testLegacySessionRestoresThroughDatabaseAndUpgradesRecord(): void
    {
        session()->set('userId', 7);
        self::assertTrue(auth()->check());
        self::assertSame(7, user()->get()->getId());
        self::assertSame(['provider' => 'database', 'identifier' => '7'], session()->get('auth'));
        auth()->reset();
        self::assertTrue(auth()->can(Permission::PAGES_EDIT));
    }

    public function testDeletedIdentityRemovesNewAndLegacyState(): void
    {
        session()->set('userId', 999);
        self::assertFalse(auth()->check());
        self::assertNull(user());
        self::assertNull(session()->get('userId'));
        self::assertNull(session()->get('auth'));
    }

    public function testMalformedNewSessionCannotFallBackToLegacyUserId(): void
    {
        session()->set('auth', ['provider' => 'database', 'identifier' => []]);
        session()->set('userId', 7);
        self::assertFalse(auth()->check());
        self::assertNull(session()->get('userId'));
        auth()->reset();
        self::assertFalse(auth()->check());
    }

    public function testLogoutPageClearsActorAndLegacyState(): void
    {
        auth()->authenticate(new PasswordCredentials('alice', 'correct-password'), 'database');
        $pages = require BASE_PATH . '/src/Resources/pages/auth.php';
        $logout = array_values(array_filter($pages, static fn(Page $page) => $page->id() === 'auth.logout'))[0];
        $response = ($logout->content())();
        self::assertSame(302, $response->getStatusCode());
        self::assertFalse(auth()->check());
        self::assertNull(user());
        self::assertNull(session()->get('userId'));
        self::assertNull(session()->get('auth'));
    }

    public function testPageAnyPermissionsStillRequireExtraHandlerPermission(): void
    {
        user(new UserHelper(null, [Permission::PAGES_EDIT], [Role::ADMIN], true));
        $page = Page::create('probe')->requireRole(Role::ADMIN)
            ->requireAnyPermission(Permission::PAGES_CREATE, Permission::PAGES_EDIT);
        (new PageAuthorization())->authorize($page);
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(403);
        (new PageAuthorization())->authorize($page, Permission::PAGES_DELETE);
    }

    public function testApiAllPermissionsRemainRequired(): void
    {
        user(new UserHelper(null, [Permission::PAGES_EDIT], [], true));
        $endpoint = ApiEndpoint::get('probe', '/api/probe')
            ->requirePermission(Permission::PAGES_EDIT)->requirePermission(Permission::PAGES_DELETE);
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(403);
        (new ApiAuthorization())->authorize($endpoint);
    }

    public function testApiAnyPermissionAllowsMatchingActor(): void
    {
        user(new UserHelper(null, [Permission::PAGES_EDIT], [], true));
        (new ApiAuthorization())->authorize(ApiEndpoint::get('probe', '/api/probe')
            ->requireAnyPermission(Permission::PAGES_CREATE, Permission::PAGES_EDIT));
        $this->addToAssertionCount(1);
    }

    public function testApiGuestReceives401JsonWithoutLoginRedirect(): void
    {
        JsonRequest::bind([], '/api/probe');
        try {
            (new ApiAuthorization())->authorize(ApiEndpoint::get('probe', '/api/probe')->requireAuthentication());
            self::fail('Expected authentication failure.');
        } catch (AuthException $exception) {
            self::assertSame(401, $exception->getCode());
            $response = (new AuthRedirectEvent())->onException($exception);
            self::assertSame(401, $response->getStatusCode());
            self::assertFalse($response->hasHeader('Location'));
            self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        }
    }

    public function testApiAuthenticatedDenialReceives403Json(): void
    {
        JsonRequest::bind([], '/api/probe');
        user(new UserHelper(null, [], [], true));
        $response = (new AuthRedirectEvent())->onException(new AuthException('Permission denied.', 403));
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Permission denied.', (string) $response->getBody());
    }

    public function testReviewCannotAttributeActionToFirstStoredUser(): void
    {
        user(new UserHelper(null, [Permission::ARTICLES_REVIEW], [], true));
        JsonRequest::bind(['action' => 'approve']);
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(401);
        (new ReviewArticle())('1', 'de');
    }
}

final class MigrationRateLimiter implements RateLimiterInterface
{
    public bool $allowed = true;
    public int $failures = 0;
    public int $successes = 0;
    public function inspect(string $username, string $ipAddress): RateLimitDecision
    { return new RateLimitDecision($this->allowed, 0, $this->allowed ? 0 : 30); }
    public function recordFailure(string $username, string $ipAddress): RateLimitDecision
    { $this->failures++; return new RateLimitDecision(true, 4, 0); }
    public function clearSuccessfulPair(string $username, string $ipAddress): void { $this->successes++; }
}
