<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Theme;

use NixPHP\CMS\Services\Theme\ThemeAssetWriter;
use PHPUnit\Framework\TestCase;

final class ThemeAssetWriterRuntimeTest extends TestCase
{
    public function testThemeCssIsWrittenAtomicallyBelowHostPublicRoot(): void
    {
        $public = sys_get_temp_dir() . '/nixcms-theme-assets-' . bin2hex(random_bytes(6));
        (new ThemeAssetWriter(publicRoot: $public))->write('My Theme', "a {\r\n color: red;\r\n}\r\n");

        $path = $public . '/css/themes/my-theme.css';
        self::assertSame("a {\n color: red;\n}\n", file_get_contents($path));
        self::assertSame(0644, fileperms($path) & 0777);
        self::assertSame([], glob(dirname($path) . '/*.tmp') ?: []);
    }
}
