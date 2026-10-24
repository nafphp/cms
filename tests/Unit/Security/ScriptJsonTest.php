<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Naf\CMS\Handlers\Api\User\UserPayloadValidator;
use Naf\CMS\Support\ScriptJson;
use Naf\View\Core\View;
use PHPUnit\Framework\TestCase;

final class ScriptJsonTest extends TestCase
{
    public function testScriptEncodingPreservesDataWithoutAllowingHtmlTermination(): void
    {
        $value = ['username' => 'x</script><b>y', 'name' => '<!-- & " \' Ü'];
        $json  = ScriptJson::encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('<', $json);
        self::assertStringNotContainsString('>', $json);
        self::assertSame($value, json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    public function testUsersTemplateKeepsUntrustedNamesInsideItsScript(): void
    {
        $view = new class extends View {
            public function setLayout(string $template): View
            {
                return $this;
            }
        };
        $view->setTemplate('admin.users.index')->setVariables([
            'users'           => [['id' => 42, 'username' => 'x</script><b>y', 'firstname' => '</script><img src=x>']],
            'roleDefinitions' => [['name' => '</script><h1>role']],
        ])->render();
        $html = $view->renderBlock('content');
        self::assertSame(1, preg_match_all('~</script\s*>~i', $html));
        self::assertStringNotContainsString('x</script><b>y', $html);
        self::assertStringContainsString('\\u003C/script\\u003E', $html);
    }

    public function testUserPayloadRejectsMarkupAndOverlongUsernames(): void
    {
        $validator = new UserPayloadValidator();
        foreach (['x</script><b>y', 'name with spaces', str_repeat('a', 65)] as $username) {
            self::assertArrayHasKey('username', $validator->validate(['username' => $username], false, false));
        }
        foreach (['Üser-42', 'ada@example.test', 'some.user_42'] as $username) {
            self::assertSame([], $validator->validate(['username' => $username], false, false));
        }
    }
}
