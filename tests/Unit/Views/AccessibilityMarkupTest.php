<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

final class AccessibilityMarkupTest extends TestCase
{
    public function testLayoutsProvideSkipLinksAndMainLandmarks(): void
    {
        foreach (['admin', 'auth', 'page'] as $layout) {
            $source = (string) file_get_contents(BASE_PATH . '/views/layouts/' . $layout . '.phtml');
            self::assertStringContainsString('href="#content"', $source);
            if ($layout !== 'page') {
                self::assertStringContainsString('id="content" tabindex="-1"', $source);
            }
        }
        foreach (glob(BASE_PATH . '/views/page/templates/*.phtml') ?: [] as $file) {
            self::assertStringContainsString('id="content" tabindex="-1"', (string) file_get_contents($file));
        }
    }
}
