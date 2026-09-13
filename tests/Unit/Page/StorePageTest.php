<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use Naf\Database\Core\Database;
use Naf\CMS\Handlers\Page\Pages\StorePage;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\JsonRequest;
use function Naf\app;

final class StorePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->container()->set(Database::class, static fn(): ?Database => null);
    }

    public function testInvokeRejectsAnIncompleteBuilderPayloadBeforePersistence(): void
    {
        JsonRequest::bind([]);
        $response = (new StorePage())();
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        foreach (['builderVersion', 'title', 'path', 'templateId', 'language', 'blocks'] as $field) {
            self::assertArrayHasKey($field, $payload['errors']);
        }
    }

    public function testCustomLayoutDefinitionsAreNormalizedForPersistence(): void
    {
        $handler = new StorePage();
        $method = new ReflectionMethod($handler, 'normalizeCustomLayout');
        $errors = [];

        $normalized = $method->invokeArgs($handler, [[
            [
                'id' => 'slot_hero',
                'label' => 'Hero',
                'region' => 'header',
                'size' => 'hero',
                'position' => 'center',
                'maxHeight' => '640',
                'overflow' => 'auto',
                'surface' => 'card',
                'accepts' => ['hero', 'hero', 'Bad Type'],
                'defaultContentType' => 'hero',
                'unexpected' => 'ignored',
            ],
        ], &$errors]);

        self::assertSame([], $errors);
        self::assertSame([[
            'id' => 'slot_hero',
            'label' => 'Hero',
            'region' => 'header',
            'size' => 'hero',
            'position' => 'center',
            'maxHeight' => '640',
            'overflow' => 'auto',
            'surface' => 'card',
            'accepts' => ['hero'],
            'defaultContentType' => 'hero',
        ]], $normalized);
    }

    public function testCustomLayoutRejectsDuplicateSlotIds(): void
    {
        $handler = new StorePage();
        $method = new ReflectionMethod($handler, 'normalizeCustomLayout');
        $errors = [];

        $normalized = $method->invokeArgs($handler, [[
            ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
            ['id' => 'slot_text', 'label' => 'Duplicate', 'region' => 'main', 'size' => 'medium'],
        ], &$errors]);

        self::assertSame([[
            'id' => 'slot_text',
            'label' => 'Text',
            'region' => 'main',
            'size' => 'medium',
            'position' => 'stretch',
            'maxHeight' => 'none',
            'overflow' => 'visible',
            'surface' => 'none',
        ]], $normalized);
        self::assertStringContainsString('duplicate slot id "slot_text"', implode("\n", $errors));
    }

    public function testNormalizeBlocksAllowsCustomLayoutSlotIds(): void
    {
        $handler = new StorePage();
        $method = new ReflectionMethod($handler, 'normalizeBlocks');

        $normalized = $method->invoke($handler, [
            'slot_text' => ['type' => 'text', 'props' => ['text' => 'Hello']],
            'not_in_layout' => ['type' => 'text', 'props' => []],
        ], [
            ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
        ]);

        self::assertSame([
            'slot_text' => [
                'type' => 'text',
                'label' => 'Text block',
                'previewTitle' => 'Short text',
                'props' => ['text' => 'Hello'],
            ],
        ], $normalized);
    }

    public function testValidationRejectsUnsafeCanonicalUrl(): void
    {
        $handler = new StorePage();
        $templatesMethod = new ReflectionMethod($handler, 'templates');
        $templates = $templatesMethod->invoke($handler);
        self::assertNotSame([], $templates);
        $templateId = (string) array_key_first($templates);

        $validate = new ReflectionMethod($handler, 'validate');
        $errors = $validate->invoke($handler, [
            'builderVersion' => \Naf\CMS\Page\PageBuilderPayloadNormalizer::VERSION,
            'title' => 'Safe page title',
            'path' => '/canonical-policy-probe',
            'language' => 'en',
            'templateId' => $templateId,
            'blocks' => [],
            'meta' => ['seo' => ['canonicalUrl' => 'javascript:alert(1)']],
        ], $templates);

        self::assertArrayHasKey('meta.seo.canonicalUrl', $errors);
    }
}
