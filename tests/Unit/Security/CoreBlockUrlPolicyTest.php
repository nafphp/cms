<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function NixPHP\View\s;

final class CoreBlockUrlPolicyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!function_exists('NixPHP\I18n\t')) {
            eval('namespace NixPHP\I18n; function t(string $value): string { return $value; }');
        }
    }

    #[DataProvider('storedUrlViews')]
    public function testStoredActiveUrlsAreNotRenderedByCoreBlockViews(string $view, array $props): void
    {
        $html = $this->render($view, ['content' => ['props' => $props]]);

        self::assertStringNotContainsString('javascript:', strtolower($html));
        self::assertStringNotContainsString('data:text/html', strtolower($html));
        self::assertStringNotContainsString('data:image', strtolower($html));
    }

    public static function storedUrlViews(): iterable
    {
        yield 'CTA link' => ['cta', ['buttonLabel' => 'Open', 'buttonUrl' => 'javascript:alert(1)']];
        yield 'hero link and image' => ['hero', ['buttonLabel' => 'Open', 'buttonUrl' => 'javascript:alert(1)', 'image' => 'data:image/svg+xml,<svg onload=alert(1)>']];
        yield 'image source' => ['image', ['src' => 'javascript:alert(1)']];
        yield 'media source' => ['media', ['src' => 'data:image/svg+xml,<svg onload=alert(1)>']];
        yield 'embed source' => ['embed', ['url' => 'javascript:alert(1)']];
        yield 'map source' => ['map', ['embedUrl' => 'data:text/html,<script>alert(1)</script>']];
    }

    public function testStoredNavigationUrlUsesTheSamePolicy(): void
    {
        require_once BASE_PATH . '/views/page/blocks/_helpers/navigation.php';

        $html = \nixcms_page_block_nav([['href' => 'javascript:alert(1)', 'label' => 'Unsafe']]);

        self::assertStringContainsString('href="#"', $html);
        self::assertStringNotContainsString('javascript:', strtolower($html));
        self::assertSame('&lt;marker&gt;', s('<marker>'));
    }

    public function testArticleListingViewsUseTheCentralLinkPolicy(): void
    {
        foreach (['articles.phtml', 'article_related.phtml'] as $view) {
            $source = file_get_contents(BASE_PATH . '/views/page/blocks/' . $view);
            self::assertIsString($source);
            self::assertStringContainsString('UrlPolicy::link', $source);
        }
    }

    /** @param array<string, mixed> $variables */
    private function render(string $view, array $variables): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/page/blocks/' . $view . '.phtml';
        return (string) ob_get_clean();
    }
}
