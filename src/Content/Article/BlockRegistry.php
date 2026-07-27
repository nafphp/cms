<?php

declare(strict_types=1);

namespace src\Content\Article;

use use src\Content\Article\Block\BlockInterface;
use src\Content\Article\Block\CodeBlock;
use src\Content\Article\Block\EmbedBlock;
use src\Content\Article\Block\HeaderBlock;
use src\Content\Article\Block\ImageBlock;
use src\Content\Article\Block\ListBlock;
use src\Content\Article\Block\ParagraphBlock;
use src\Content\Article\Block\QuoteBlock;
use src\Content\Article\Block\TableBlock;

/**
 * Registry mapping Editor.js block type strings to BlockInterface classes.
 * Plugins register custom blocks or override defaults via register().
 */
final class BlockRegistry
{
    /** @var array<string, class-string<BlockInterface>> */
    private array $blocks = [];

    /** @var array<class-string<BlockInterface>, BlockInterface> */
    private array $instances = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * @param class-string<BlockInterface> $blockClass
     */
    public function register(string $type, string $blockClass): void
    {
        if (!is_subclass_of($blockClass, BlockInterface::class)) {
            throw new \InvalidArgumentException(
                "Block class {$blockClass} must implement " . BlockInterface::class
            );
        }

        $this->blocks[$type] = $blockClass;
        unset($this->instances[$blockClass]);
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    public function resolve(string $type): ?BlockInterface
    {
        $class = $this->blocks[$type] ?? null;
        if ($class === null) {
            return null;
        }

        return $this->instances[$class] ??= new $class();
    }

    /**
     * @return array<string, class-string<BlockInterface>>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    private function registerDefaults(): void
    {
        $this->register('header', HeaderBlock::class);
        $this->register('paragraph', ParagraphBlock::class);
        $this->register('list', ListBlock::class);
        $this->register('quote', QuoteBlock::class);
        $this->register('code', CodeBlock::class);
        $this->register('table', TableBlock::class);
        $this->register('image', ImageBlock::class);
        $this->register('embed', EmbedBlock::class);
    }
}
