<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Naf\CMS\Handlers\Page\Auth\RegisterInvitedUser;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

final class InvitedUsernameTest extends DatabaseTestCase
{
    public function testInvitationRejectsMarkupBeforeCreatingAnAccountOrConsumingTheInvite(): void
    {
        $this->createSchema(
            'CREATE TABLE user_invites (id INTEGER PRIMARY KEY, token_hash TEXT, email TEXT, roles_json TEXT, expires_at TEXT, used_at TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, password TEXT)',
        );
        $this->insertRow('user_invites', [
            'token_hash' => hash('sha256', 'invite-token'),
            'email'      => 'invite@example.test',
            'roles_json' => '["viewer"]',
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'used_at'    => null,
        ]);
        JsonRequest::bind([
            'email'                 => 'invite@example.test', 'username' => 'x</script><b>y',
            'password'              => 'A sufficiently long password!',
            'password_confirmation' => 'A sufficiently long password!', '_csrf' => 'fixture-token',
        ]);
        $response = (new RegisterInvitedUser())('invite-token');
        self::assertStringContainsString('for the username.', (string) $response->getBody());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertNull($this->pdo->query('SELECT used_at FROM user_invites')->fetchColumn());
    }
}
