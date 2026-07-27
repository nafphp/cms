<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assets;

use NixPHP\CMS\Services\Assets\AssetPublisher;
use PHPUnit\Framework\TestCase;

final class AssetPublisherTest extends TestCase
{
    public function testPublishIsRepeatableUpdatesOwnedFilesAndRejectsConflicts(): void
    {
        $root = sys_get_temp_dir() . '/nixcms-assets-' . bin2hex(random_bytes(6));
        $source = $root . '/package';
        $public = $root . '/public';
        mkdir($source . '/css', 0777, true);
        file_put_contents($source . '/css/nixcms.css', 'v1');

        $publisher = new AssetPublisher($source, $public);
        self::assertSame(1, $publisher->publish()['published']);
        self::assertTrue($publisher->status()['current']);
        self::assertSame(0, $publisher->publish()['published']);

        file_put_contents($source . '/css/nixcms.css', 'v2');
        self::assertSame(['css/nixcms.css'], $publisher->status()['outdated']);
        self::assertSame(1, $publisher->publish()['published']);
        self::assertSame('v2', file_get_contents($public . '/css/nixcms.css'));

        file_put_contents($public . '/css/nixcms.css', 'host-owned');
        self::assertSame(['css/nixcms.css'], $publisher->status()['conflicts']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Asset publish conflicts: css/nixcms.css');
        $publisher->publish();
    }

    public function testRemoveDeletesOnlyManifestOwnedUnchangedFiles(): void
    {
        $root = sys_get_temp_dir() . '/nixcms-assets-remove-' . bin2hex(random_bytes(6));
        mkdir($root . '/package/js', 0777, true);
        file_put_contents($root . '/package/js/app.js', 'owned');
        $publisher = new AssetPublisher($root . '/package', $root . '/public');
        $publisher->publish();

        self::assertSame(1, $publisher->remove());
        self::assertFileDoesNotExist($root . '/public/js/app.js');
        self::assertFileDoesNotExist($root . '/public/.nixcms-assets.json');
    }

    public function testInstallationOwnedThemeAssetsAreNeverPublished(): void
    {
        $root = sys_get_temp_dir() . '/nixcms-assets-themes-' . bin2hex(random_bytes(6));
        mkdir($root . '/package/css/themes', 0777, true);
        mkdir($root . '/package/js/themes', 0777, true);
        file_put_contents($root . '/package/css/themes/custom.css', 'installation css');
        file_put_contents($root . '/package/js/themes/custom.js', 'alert(1)');
        file_put_contents($root . '/package/css/nixcms.css', 'package css');

        $publisher = new AssetPublisher($root . '/package', $root . '/public');

        self::assertSame(1, $publisher->publish()['published']);
        self::assertFileExists($root . '/public/css/nixcms.css');
        self::assertFileDoesNotExist($root . '/public/css/themes/custom.css');
        self::assertFileDoesNotExist($root . '/public/js/themes/custom.js');
    }
}
