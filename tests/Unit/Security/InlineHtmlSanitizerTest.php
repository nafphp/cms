<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use NixPHP\CMS\Security\InlineHtmlSanitizer;
use PHPUnit\Framework\TestCase;
use function NixPHP\View\s;

final class InlineHtmlSanitizerTest extends TestCase
{
    public function testRemovesEventStyleAndUnknownAttributes(): void
    {
        $html = (new InlineHtmlSanitizer())->sanitize(
            '<strong onclick="alert(1)" style="color:red" data-test="x">Safe</strong>'
            . '<a href="/docs" onmouseover="alert(2)" srcdoc="<script>alert(3)</script>">Docs</a>',
        );

        self::assertSame('<strong>Safe</strong><a href="/docs">Docs</a>', $html);
    }

    public function testRemovesActiveLinkTargets(): void
    {
        $html = (new InlineHtmlSanitizer())->sanitize(
            '<a href="java&#x73;cript:alert(1)" onclick="alert(2)" target="_blank">Unsafe</a>',
        );

        self::assertSame('<a>Unsafe</a>', $html);
    }

    public function testKeepsSafeInlineFormattingAndHardensBlankTargets(): void
    {
        $html = (new InlineHtmlSanitizer())->sanitize(
            '<strong>Bold</strong> <em>em</em><br><a href="https://example.test" title="Example" target="_blank" rel="opener">Link</a>',
        );

        self::assertSame(
            '<strong>Bold</strong> <em>em</em><br><a href="https://example.test" title="Example" target="_blank" rel="noopener noreferrer">Link</a>',
            $html,
        );
    }

    public function testUnwrapsDisallowedElementsWithoutKeepingActiveAttributes(): void
    {
        $html = (new InlineHtmlSanitizer())->sanitize(
            '<iframe src="https://example.test" srcdoc="<script>alert(1)</script>">Fallback</iframe><img src=x onerror=alert(2)>',
        );

        self::assertSame('Fallback', $html);
    }

    public function testEscapesTextAndAllowedAttributeValuesWithCoreHelperSemantics(): void
    {
        $html = (new InlineHtmlSanitizer())->sanitize(
            '<strong>&lt;safe&gt; &amp; text</strong><a href="/docs?a=1&amp;b=2" title="A &amp; B">Docs</a>',
        );

        self::assertSame(
            '<strong>' . s('<safe> & text') . '</strong><a href="' . s('/docs?a=1&b=2') . '" title="' . s('A & B') . '">Docs</a>',
            $html,
        );
    }
}
