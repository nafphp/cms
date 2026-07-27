<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use src\Content\Article\Block\BlockInterface;
use src\Content\Article\Block\HeaderBlock;
use src\Content\Article\Block\ImageBlock;
use src\Content\Article\Block\ParagraphBlock;
use src\Content\Article\BlockRegistry;
use PHPUnit\Framework\TestCase;

final class BlockRegistryTest extends TestCase
{
    public function testDefaultBlocksAreRegistered(): void
    {
        $registry = new BlockRegistry();

        foreach (['header', 'paragraph', 'list', 'quote', 'code', 'table', 'image', 'embed'] as $type) {
            self::assertTrue($registry->has($type), "Default block '{$type}' should be registered");
            self::assertInstanceOf(BlockInterface::class, $registry->resolve($type));
        }
    }

    public function testResolveReturnsNullForUnknownType(): void
    {
        $registry = new BlockRegistry();

        self::assertNull($registry->resolve('does-not-exist'));
        self::assertFalse($registry->has('does-not-exist'));
    }

    public function testResolveMemoizesInstancesPerClass(): void
    {
        $registry = new BlockRegistry();

        self::assertSame($registry->resolve('header'), $registry->resolve('header'));
    }

    public function testRegisterAddsCustomBlock(): void
    {
        $registry = new BlockRegistry();
        $registry->register('callout', HeaderBlock::class);

        self::assertTrue($registry->has('callout'));
        self::assertInstanceOf(HeaderBlock::class, $registry->resolve('callout'));
    }

    public function testRegisterOverridesExistingType(): void
    {
        $registry = new BlockRegistry();
        $registry->register('image', HeaderBlock::class);

        self::assertInstanceOf(HeaderBlock::class, $registry->resolve('image'));
        self::assertNotInstanceOf(ImageBlock::class, $registry->resolve('image'));
    }

    public function testRegisterRejectsNonBlockClass(): void
    {
        $registry = new BlockRegistry();

        self::expectException(\InvalidArgumentException::class);
        $registry->register('bad', \stdClass::class);
    }

    public function testAllReturnsTypeToClassMap(): void
    {
        $registry = new BlockRegistry();
        $map = $registry->all();

        self::assertSame(ParagraphBlock::class, $map['paragraph'] ?? null);
        self::assertSame(ImageBlock::class, $map['image'] ?? null);
    }
}
