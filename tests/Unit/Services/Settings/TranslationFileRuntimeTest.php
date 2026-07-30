<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Settings;

use NixPHP\CMS\Localization\RuntimeTranslator;
use NixPHP\CMS\Services\Settings\TranslationFileService;
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

    public function testMergeOnlyTouchesTheGivenKeys(): void
    {
        [$service, $defaults, $overrides] = $this->service([
            'Hello' => 'Package',
            'Only default' => 'Yes',
        ], [
            'Hello' => 'Host',
        ]);

        $result = $service->merge('en', ['Greeting' => 'Moin']);

        self::assertTrue($result['success']);
        // Existing override kept, default untouched, only the new key added.
        self::assertSame(
            ['Greeting' => 'Moin', 'Hello' => 'Host'],
            $this->overrideFile($overrides),
        );
        self::assertSame(['Hello' => 'Package', 'Only default' => 'Yes'], $this->overrideFile($defaults));
        self::assertSame(1, $result['changed']);
    }

    public function testMergeDoesNotTurnDefaultsIntoOverrides(): void
    {
        [$service, , $overrides] = $this->service(['Hello' => 'Package', 'Only default' => 'Yes'], null);

        $service->merge('en', ['Hello' => 'Host']);

        self::assertSame(['Hello' => 'Host'], $this->overrideFile($overrides));
    }

    public function testMergeCanRemoveAnOverrideSoTheDefaultAppliesAgain(): void
    {
        [$service, , $overrides] = $this->service(['Hello' => 'Package'], ['Hello' => 'Host', 'Extra' => 'Keep']);

        $service->merge('en', [], ['Hello']);

        self::assertSame(['Extra' => 'Keep'], $this->overrideFile($overrides));
        self::assertSame('Package', $service->entries('en')['Hello']);
    }

    public function testMergeWithoutEntriesCreatesTheFileButKeepsExistingOverrides(): void
    {
        [$service, , $overrides] = $this->service(['Hello' => 'Package'], ['Hello' => 'Host']);

        // "Create language" on a language that already has overrides.
        $result = $service->merge('en', []);

        self::assertTrue($result['success']);
        self::assertSame(['Hello' => 'Host'], $this->overrideFile($overrides));
    }

    public function testSaveStillReplacesTheWholeOverrideFile(): void
    {
        [$service, , $overrides] = $this->service(['Hello' => 'Package'], ['Hello' => 'Host', 'Extra' => 'Drop me']);

        $service->save('en', ['Hello' => 'Host']);

        self::assertSame(['Hello' => 'Host'], $this->overrideFile($overrides));
    }

    /**
     * @param array<string, string> $defaults
     * @param array<string, string>|null $overrides
     * @return array{0: TranslationFileService, 1: string, 2: string}
     */
    private function service(array $defaults, ?array $overrides): array
    {
        $root = sys_get_temp_dir() . '/nixcms-translations-' . bin2hex(random_bytes(6));
        $defaultDirectory = $root . '/defaults';
        $overrideDirectory = $root . '/runtime';
        mkdir($defaultDirectory, 0777, true);
        mkdir($overrideDirectory, 0777, true);

        file_put_contents($defaultDirectory . '/en.json', json_encode($defaults, JSON_THROW_ON_ERROR));
        if ($overrides !== null) {
            file_put_contents($overrideDirectory . '/en.json', json_encode($overrides, JSON_THROW_ON_ERROR));
        }

        return [
            new TranslationFileService($overrideDirectory, $defaultDirectory),
            $defaultDirectory,
            $overrideDirectory,
        ];
    }

    /** @return array<string, string> */
    private function overrideFile(string $directory): array
    {
        return json_decode((string) file_get_contents($directory . '/en.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
