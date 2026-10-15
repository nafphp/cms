<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Naf\CMS\Security\UrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlPolicyTest extends TestCase
{
    #[DataProvider('unsafeLinks')]
    public function testLinkPolicyRejectsActiveAndProtocolRelativeUrls(string $url): void
    {
        self::assertNull(UrlPolicy::link($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeLinks(): iterable
    {
        yield 'javascript CTA' => ['javascript:alert(1)'];
        yield 'entity-obfuscated navigation' => ['java&#x73;cript:alert(1)'];
        yield 'protocol-relative hero link' => ['//attacker.example/path'];
        yield 'data article detail URL' => ['data:text/html,<script>alert(1)</script>'];
        yield 'control character URL' => ["https://example.test/\nattack"];
    }

    public function testLinkPolicyAllowsExpectedNavigationTargets(): void
    {
        self::assertSame('/articles/example?preview=1#content', UrlPolicy::link('/articles/example?preview=1#content'));
        self::assertSame('#content', UrlPolicy::link('#content'));
        self::assertSame('https://example.test/path', UrlPolicy::link('https://example.test/path'));
        self::assertSame('mailto:editor@example.test', UrlPolicy::link('mailto:editor@example.test'));
        self::assertSame('tel:+49 123 4567', UrlPolicy::link('tel:+49 123 4567'));
    }

    public function testMediaPolicyAllowsOnlyInternalAndHttpSources(): void
    {
        self::assertSame('/media/example/image.jpg', UrlPolicy::media('/media/example/image.jpg'));
        self::assertSame('https://cdn.example.test/image.jpg', UrlPolicy::media('https://cdn.example.test/image.jpg'));
        self::assertNull(UrlPolicy::media('data:image/svg+xml,<svg onload=alert(1)>'));
        self::assertNull(UrlPolicy::media('javascript:alert(1)'));
    }

    public function testEmbedPolicyAllowsOnlyAbsoluteHttpUrls(): void
    {
        self::assertSame('https://video.example.test/embed/1', UrlPolicy::embed('https://video.example.test/embed/1'));
        self::assertNull(UrlPolicy::embed('/internal/frame'));
        self::assertNull(UrlPolicy::embed('file:///etc/passwd'));
        self::assertNull(UrlPolicy::embed('javascript:alert(1)'));
    }
}
