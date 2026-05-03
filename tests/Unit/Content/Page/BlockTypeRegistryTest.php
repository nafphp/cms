<?php

declare(strict_types=1);

namespace Tests\Unit\Content\Page;

use NixCMS\Content\Page\Block\AbstractBlockType;
use NixCMS\Content\Page\Block\BlockTypeRegistry;
use PHPUnit\Framework\TestCase;

final class BlockTypeRegistryTest extends TestCase
{
    public function testDefaultsExposeBuilderBlockTypes(): void
    {
        $registry = new BlockTypeRegistry();
        $registry->addMany(require BASE_PATH . '/app/blockTypes.php');

        self::assertSame('Hero', $registry->require('hero')->label());
        self::assertSame(['main', 'aside', 'footer'], $registry->require('form')->regions());
        self::assertSame('Section', $registry->require('section')->label());
        self::assertSame(['hero', 'header', 'main', 'aside', 'footer'], $registry->require('stack')->regions());

        $builderTypes = $registry->builderTypes();
        $section = $this->findBuilderType($builderTypes, 'section');
        $hero = $this->findBuilderType($builderTypes, 'hero');
        $columns = $this->findBuilderType($builderTypes, 'columns');
        $articleContent = $this->findBuilderType($builderTypes, 'article-content');

        self::assertSame([], $section['defaults']['children']);
        self::assertSame('Hero', $hero['label']);
        self::assertSame('A clear page hero', $hero['defaults']['title']);
        self::assertSame(2, $columns['defaults']['columns']);
        self::assertSame(['hero', 'main'], $articleContent['regions']);
    }

    public function testAddRegistersPluginBlockType(): void
    {
        $registry = new BlockTypeRegistry();

        $registry->add(new class extends AbstractBlockType {
            public function id(): string
            {
                return 'mycustom';
            }

            public function label(): string
            {
                return 'My custom block';
            }

            public function regions(): array
            {
                return ['main'];
            }
        });

        self::assertSame('My custom block', $registry->require('mycustom')->label());
        self::assertSame('mycustom', $registry->builderTypes()[0]['type']);
    }

    public function testDuplicateBlockTypesRequireExplicitReplacement(): void
    {
        $registry = new BlockTypeRegistry();
        $type = new class extends AbstractBlockType {
            public function id(): string
            {
                return 'duplicate';
            }

            public function label(): string
            {
                return 'Duplicate';
            }

            public function regions(): array
            {
                return ['main'];
            }
        };

        $registry->add($type);

        self::expectException(\LogicException::class);
        $registry->add($type);
    }

    /**
     * @param array<int, array<string, mixed>> $types
     * @return array<string, mixed>
     */
    private function findBuilderType(array $types, string $id): array
    {
        foreach ($types as $type) {
            if (($type['type'] ?? null) === $id) {
                return $type;
            }
        }

        self::fail(sprintf('Builder block type "%s" was not found.', $id));
    }
}
