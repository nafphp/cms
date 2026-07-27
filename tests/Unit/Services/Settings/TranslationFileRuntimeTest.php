<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Settings;

use src\Localization\RuntimeTranslator;
use src\Services\Settings\TranslationFileService;
use PHPUnit\Framework\TestCase;

final class TranslationFileRuntimeTest extends TestCase
{
    public function testOverridesMergeOverImmutableDefaultsAndSaveAtomically(): void
    {
        $root = sys_get_temp_dir() . '/nixcms-translations-' . bin2hex(random_bytes(6));
        $defaults = $root . '/defaults';
        $overrides = $root . '/runtime';
        mkdir($defaults, 0777, true);
        mkdir($overrides, 0777, true);
        file_put_contents($defaults . '/en.json', json_encode(['Hello' => 'Package', 'Only default' => 'Yes'], JSON_THROW_ON_ERROR));
        file_put_contents($overrides . '/en.json', json_encode(['Hello' => 'Host'], JSON_THROW_ON_ERROR));

        $service = new TranslationFileService($overrides, $defaults);
        self::assertSame(['Hello' => 'Host', 'Only default' => 'Yes'], $service->entries('en'));
        self::assertSame('Host', (new RuntimeTranslator('en', $service))->translate('Hello'));

        $result = $service->save('en', ['Hello' => 'Updated', 'Only default' => 'Yes']);

        self::assertTrue($result['success']);
        self::assertSame('Package', json_decode((string) file_get_contents($defaults . '/en.json'), true)['Hello']);
        self::assertSame('Updated', json_decode((string) file_get_contents($overrides . '/en.json'), true)['Hello']);
        self::assertSame(0640, fileperms($overrides . '/en.json') & 0777);
    }
}
