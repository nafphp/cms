<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use NixPHP\CMS\Page\CustomLayoutNormalizer;
use PHPUnit\Framework\TestCase;

final class CustomLayoutNormalizerTest extends TestCase
{
    private CustomLayoutNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CustomLayoutNormalizer();
    }

    public function testRejectsInvalidId(): void
    {
        foreach (['', '1starts-with-number', 'contains space', str_repeat('a', 65)] as $id) {
            $errors = [];

            $result = $this->normalizer->normalize([
                ['id' => $id, 'label' => 'Hero'],
            ], $errors);

            self::assertSame([], $result);
            self::assertSame('Custom layout slot 1 needs a valid id.', $errors[0] ?? null);
        }
    }

    public function testRejectsDuplicateIds(): void
    {
        $errors = [];

        $result = $this->normalizer->normalize([
            ['id' => 'hero', 'label' => 'Hero'],
            ['id' => 'hero', 'label' => 'Duplicate Hero'],
        ], $errors);

        self::assertCount(1, $result);
        self::assertSame('Custom layout contains duplicate slot id "hero".', $errors[0] ?? null);
    }

    public function testRejectsInvalidRegion(): void
    {
        $errors = [];

        $result = $this->normalizer->normalize([
            ['id' => 'hero', 'label' => 'Hero', 'region' => 'sidebar'],
        ], $errors);

        self::assertSame([], $result);
        self::assertSame('Custom layout slot "hero" uses an invalid region.', $errors[0] ?? null);
    }

    public function testFiltersInvalidAcceptsEntries(): void
    {
        $errors = [];

        $result = $this->normalizer->normalize([
            [
                'id' => 'hero',
                'label' => 'Hero',
                'accepts' => ['hero', 'InvalidCaps', '../path', 'text', 'hero'],
                'defaultContentType' => '../path',
            ],
        ], $errors);

        self::assertSame([], $errors);
        self::assertSame(['hero', 'text'], $result[0]['accepts'] ?? null);
        self::assertArrayNotHasKey('defaultContentType', $result[0]);
    }

    public function testAppliesDefaultsForOmittedOptions(): void
    {
        $errors = [];

        $result = $this->normalizer->normalize([
            ['id' => 'hero', 'label' => 'Hero'],
        ], $errors);

        self::assertSame([], $errors);
        self::assertSame([
            'id' => 'hero',
            'label' => 'Hero',
            'region' => 'main',
            'size' => 'medium',
            'position' => 'stretch',
            'maxHeight' => 'none',
            'overflow' => 'visible',
            'surface' => 'none',
        ], $result[0]);
    }

    public function testStripsUnknownFields(): void
    {
        $errors = [];

        $result = $this->normalizer->normalize([
            [
                'id' => 'hero',
                'label' => 'Hero',
                'region' => 'header',
                'size' => 'hero',
                'unknown' => 'drop-me',
                'style' => ['background' => 'red'],
            ],
        ], $errors);

        self::assertSame([], $errors);
        self::assertSame(['id', 'label', 'region', 'size', 'position', 'maxHeight', 'overflow', 'surface'], array_keys($result[0]));
    }
}
