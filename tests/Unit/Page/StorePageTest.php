<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use src\Handlers\Page\Pages\StorePage;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class StorePageTest extends TestCase
{
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
}
