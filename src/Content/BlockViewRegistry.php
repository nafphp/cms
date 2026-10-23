<?php

declare(strict_types=1);

namespace Naf\CMS\Content;

use InvalidArgumentException;
use Naf\CMS\Content\Page\Block\ProvidesBlockViewInterface;

use function Naf\CMS\cms;
use function Naf\log;

/** @api */
final class BlockViewRegistry
{
    /** @var array<string, string> */
    private array $views = [];

    /** @var array<string, true> */
    private array $warned = [];

    public function __construct()
    {
        $this->addMany([
            'header'          => 'page.blocks.header',
            'footer'          => 'page.blocks.footer',
            'navigation'      => 'page.blocks.navigation',
            'articles'        => 'page.blocks.articles',
            'hero'            => 'page.blocks.hero',
            'cta'             => 'page.blocks.cta',
            'feature'         => 'page.blocks.feature',
            'media'           => 'page.blocks.media',
            'image'           => 'page.blocks.image',
            'embed'           => 'page.blocks.embed',
            'map'             => 'page.blocks.map',
            'quote'           => 'page.blocks.quote',
            'stats'           => 'page.blocks.stats',
            'chart'           => 'page.blocks.chart',
            'form'            => 'page.blocks.form',
            'heading'         => 'page.blocks.heading',
            'text'            => 'page.blocks.text',
            'article-title'   => 'page.blocks.article_title',
            'article-content' => 'page.blocks.article_content',
            'article-meta'    => 'page.blocks.article_meta',
            'article-related' => 'page.blocks.article_related',
            'section'         => 'page.blocks.section',
            'stack'           => 'page.blocks.stack',
            'columns'         => 'page.blocks.columns',
            'grid'            => 'page.blocks.grid',
        ]);

        $aliases = [
            'contact-form' => 'form',
            'aside'        => 'text',
        ];

        foreach ($aliases as $alias => $type) {
            $this->add($alias, $this->viewFor($type));
        }
    }

    public function add(string $type, string $view): void
    {
        $type = $this->normalize($type);
        $view = trim($view);
        if ($type === '' || $view === '') {
            throw new InvalidArgumentException('Block view type and view name must be non-empty strings.');
        }

        $this->views[$type] = $view;
    }

    /**
     * @param array<string, string> $views
     */
    public function addMany(array $views): void
    {
        foreach ($views as $type => $view) {
            $this->add((string) $type, $view);
        }
    }

    public function viewFor(string $type): string
    {
        $type = $this->normalize($type);
        if (isset($this->views[$type])) {
            return $this->views[$type];
        }

        $blockType = cms()->blockTypes()->get($type);
        if ($blockType instanceof ProvidesBlockViewInterface) {
            return $blockType->view();
        }

        if (!isset($this->warned[$type])) {
            $this->warned[$type] = true;
            log()->warning('No view registered for page block type', ['type' => $type]);
        }

        return 'page.blocks.placeholder';
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->views;
    }

    public function has(string $type): bool
    {
        $type = $this->normalize($type);

        return isset($this->views[$type]) || cms()->blockTypes()->get($type) instanceof ProvidesBlockViewInterface;
    }

    public function remove(string $type): void
    {
        unset($this->views[$this->normalize($type)]);
    }

    private function normalize(string $type): string
    {
        return strtolower(trim($type));
    }
}
