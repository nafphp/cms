<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

use function Naf\CMS\cms;

final class AuthorizationWiringTest extends TestCase
{
    /**
     * Public site visitors must be able to submit rendered forms and request
     * public media. Both handlers enforce their own payload/visibility policy.
     *
     * Countercheck: removing the permission from api.admin.summary makes
     * testEveryApiEndpointRequiresAuthorization report that endpoint by ID.
     */
    private const array INTENTIONALLY_PUBLIC = [
        'api.forms.submit',
        'media.serve',
    ];

    public function testEveryApiEndpointRequiresAuthorization(): void
    {
        $unprotected = [];
        foreach (cms()->api()->all() as $id => $endpoint) {
            if (in_array($id, self::INTENTIONALLY_PUBLIC, true)) {
                continue;
            }
            if ($endpoint->requiredPermissions() === []
                && $endpoint->requiredRoles() === []
                && !$endpoint->requiresAuthentication()
            ) {
                $unprotected[] = $id;
            }
        }
        self::assertSame([], $unprotected);
    }

    public function testWritingApiEndpointsRequirePermissionOrRole(): void
    {
        $weak = [];
        foreach (cms()->api()->all() as $id => $endpoint) {
            if ($id === 'api.forms.submit') {
                continue;
            }
            if ($endpoint->method() !== 'GET'
                && $endpoint->requiredPermissions() === []
                && $endpoint->requiredRoles() === []
            ) {
                $weak[] = $id;
            }
        }
        self::assertSame([], $weak);
    }

    public function testAdminPagesRequireAuthorization(): void
    {
        $unprotected = [];
        foreach (cms()->pages()->all() as $id => $page) {
            if (!str_starts_with($page->path(), '/admin') || $page->path() === '/admin/login') {
                continue;
            }
            if ($page->requiredPermissions() === []
                && $page->requiredRoles() === []
                && !$page->requiresAuthentication()
            ) {
                $unprotected[] = $id;
            }
        }
        self::assertSame([], $unprotected);
    }

    public function testAdminGuardListenerRemainsRegistered(): void
    {
        $bootstrap = (string) file_get_contents(dirname(__DIR__, 3) . '/bootstrap.php');
        self::assertStringContainsString(
            "event()->listen(Event::CONTROLLER_CALLING, [AdminGuardEvent::class, 'handle'])",
            $bootstrap,
        );
    }
}
