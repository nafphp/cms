<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Settings;

use NixPHP\CMS\Repositories\SettingRepository;
use NixPHP\CMS\Services\Settings\SettingsService;
use Tests\Support\DatabaseTestCase;

final class SettingsServiceTest extends DatabaseTestCase
{
    private SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(<<<SQL
            CREATE TABLE cms_settings (
                setting_key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NULL
            )
        SQL);

        $this->service = new SettingsService(new SettingRepository($this->pdo), static fn(): bool => true);
    }

    public function testSavePersistsOnlyRegisteredSettingsWithNormalizedValues(): void
    {
        $result = $this->service->save([
            'app.name' => ' Customer CMS ',
            'language' => 'de',
            'cms.languages' => ['DE', 'en', 'xx', 'de', 'FR'],
            'ai.enabled' => '1',
            'ai.type' => 'local',
            'unknown.setting' => 'ignored',
        ]);

        self::assertSame(['success' => true, 'errors' => []], $result);
        self::assertSame([
            ['setting_key' => 'ai.enabled', 'value' => '1'],
            ['setting_key' => 'ai.type', 'value' => 'local'],
            ['setting_key' => 'app.name', 'value' => 'Customer CMS'],
            ['setting_key' => 'cms.languages', 'value' => 'de,en,fr'],
            ['setting_key' => 'language', 'value' => 'de'],
        ], $this->fetchRows('SELECT setting_key, value FROM cms_settings ORDER BY setting_key ASC'));
    }

    public function testInvalidPayloadReturnsFieldErrorsAndDoesNotPersistPartialValues(): void
    {
        $result = $this->service->save([
            'app.name' => ' ',
            'language' => 'klingon',
            'cms.languages' => ['xx'],
        ]);

        self::assertFalse($result['success']);
        self::assertSame(['Invalid setting value.'], $result['errors']['app.name']);
        self::assertSame(['Invalid setting value.'], $result['errors']['language']);
        self::assertSame(['Invalid setting value.'], $result['errors']['cms.languages']);
        self::assertSame(0, (int) $this->fetchOne('SELECT COUNT(*) AS total FROM cms_settings')['total']);
    }

    public function testSectionsExposeStoredConfigAndDefaultSources(): void
    {
        $this->insertRow('cms_settings', [
            'setting_key' => 'app.name',
            'value' => 'Stored CMS',
            'updated_at' => '2026-05-05 10:00:00',
        ]);

        $sections = $this->service->sections('general');
        $application = $sections[0];
        $fields = [];
        foreach ($application['fields'] as $field) {
            $fields[$field['key']] = $field;
        }

        self::assertSame('application', $application['id']);
        self::assertSame('Stored CMS', $fields['app.name']['value']);
        self::assertSame('database', $fields['app.name']['source']);
        self::assertSame('NX', $fields['app.short_name']['value']);
        self::assertSame('config', $fields['app.short_name']['source']);
        self::assertSame('', $fields['branding.logoText']['value']);
        self::assertSame('default', $fields['branding.logoText']['source']);
    }

    public function testDedicatedSectionPermissionBlocksReadsAndWrites(): void
    {
        $service = new SettingsService(new SettingRepository($this->pdo), static fn(): bool => false);

        self::assertSame([], $service->sections('ai'));

        $result = $service->save(['ai.instructions' => 'Do something global']);

        self::assertFalse($result['success']);
        self::assertSame(
            ['You do not have permission to update this setting.'],
            $result['errors']['ai.instructions'],
        );
        self::assertSame(0, (int) $this->fetchOne('SELECT COUNT(*) AS total FROM cms_settings')['total']);
    }
}
