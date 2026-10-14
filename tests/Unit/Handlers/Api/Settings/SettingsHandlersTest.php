<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\Settings;

use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Contracts\TranslationServiceInterface;
use Naf\CMS\Handlers\Api\Settings\GetTranslations;
use Naf\CMS\Handlers\Api\Settings\UpdateSettings;
use Naf\CMS\Handlers\Api\Settings\UpdateTranslations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\JsonRequest;

final class SettingsHandlersTest extends TestCase
{
    public function testUpdateSettingsDelegatesPayloadAndMapsValidationFailure(): void
    {
        JsonRequest::bind(['app.name' => '']);
        $service  = new SettingsServiceSpy();
        $response = (new UpdateSettings($service))();

        self::assertSame(['app.name' => ''], $service->payload);
        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($this->json($response)['success']);
    }

    public function testTranslationsDefaultToReplaceAndSupportMerge(): void
    {
        $service = new TranslationServiceSpy();
        JsonRequest::bind(['entries' => ['hello' => 'Hallo']]);
        self::assertSame(200, (new UpdateTranslations($service))('de')->getStatusCode());
        self::assertSame('save', $service->lastCall);

        JsonRequest::bind(['mode' => 'merge', 'entries' => ['hello' => 'Hi'], 'remove' => ['old']]);
        (new UpdateTranslations($service))('en');
        self::assertSame('merge', $service->lastCall);
        self::assertSame(['old'], $service->remove);
    }

    public function testGetTranslationsNormalizesKnownLanguagesAndRejectsUnknownOnes(): void
    {
        $service = new TranslationServiceSpy();
        $handler = new GetTranslations($service);

        $known = $handler('DE');
        self::assertSame(200, $known->getStatusCode());
        self::assertSame('de', $this->json($known)['language']);

        $unknown = $handler('klingon');
        self::assertSame(400, $unknown->getStatusCode());
        self::assertFalse($this->json($unknown)['success']);
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

final class SettingsServiceSpy implements SettingsServiceInterface
{
    /** @var array<string, mixed> */
    public array $payload = [];

    public function sections(?string $group = null): array
    {
        return [];
    }

    public function save(array $payload): array
    {
        $this->payload = $payload;

        return ['success' => false, 'errors' => ['app.name' => ['required']]];
    }
}

final class TranslationServiceSpy implements TranslationServiceInterface
{
    public string $lastCall = '';
    /** @var array<int, string> */
    public array $remove = [];

    public function languages(string $activeLanguage = ''): array
    {
        return [];
    }

    public function entries(string $language): array
    {
        return [];
    }

    public function hasLanguage(string $language): bool
    {
        return true;
    }

    public function save(string $language, array $entries): array
    {
        $this->lastCall = 'save';

        return ['success' => true, 'errors' => [], 'changed' => count($entries)];
    }

    public function merge(string $language, array $entries, array $remove = []): array
    {
        $this->lastCall = 'merge';
        $this->remove   = $remove;

        return ['success' => true, 'errors' => [], 'changed' => count($entries)];
    }
}
