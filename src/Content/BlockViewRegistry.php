<?php

declare(strict_types=1);

namespace Naf\CMS\Content;

use InvalidArgumentException;
use Naf\CMS\Content\Page\Block\ProvidesBlockViewInterface;
use Naf\CMS\Support\ViewLocator;

use function Naf\CMS\cms;
use function Naf\log;

/** @api */
final class BlockViewRegistry
{
    /** @var array<string, string> */
    private array $views = [];

    private const string PLACEHOLDER = 'page.blocks.placeholder';

    /** @var array<string, true> */
    private array $warned = [];

    /** @var array<string, bool> */
    private array $existing = [];

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
            $this->add($alias, $this->views[$type]);
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

    /**
     * The view a block type asks for: its explicit mapping, otherwise the view
     * its block type declares. Null when neither exists. Does not check that
     * the view file is there; use viewFor() for rendering.
     */
    public function declaredView(string $type): ?string
    {
        $type = $this->normalize($type);
        if (isset($this->views[$type])) {
            return $this->views[$type];
        }

        $blockType = cms()->blockTypes()->get($type);

        return $blockType instanceof ProvidesBlockViewInterface ? $blockType->view() : null;
    }

    /**
     * The view to render a block type with
     *
     * Falls back to the placeholder, with one warning per type, when no view
     * is declared or the declared view file does not exist. A plugin block
     * without its view must not take the whole page down; integrity:check
     * reports it.
     */
    public function viewFor(string $type): string
    {
        $type = $this->normalize($type);
        $view = $this->declaredView($type);

        if ($view === null) {
            $this->warnOnce($type, 'No view registered for page block type', ['type' => $type]);

            return self::PLACEHOLDER;
        }

        if (!$this->viewExists($view)) {
            $this->warnOnce($type, 'Page block view not found', ['type' => $type, 'view' => $view]);

            return self::PLACEHOLDER;
        }

        return $view;
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
        return $this->declaredView($type) !== null;
    }

    public function remove(string $type): void
    {
        unset($this->views[$this->normalize($type)]);
    }

    private function normalize(string $type): string
    {
        return strtolower(trim($type));
    }

    private function viewExists(string $view): bool
    {
        return $this->existing[$view] ??= (new ViewLocator())->find($view) !== null;
    }

    /**
     * @param array<string, string> $context
     */
    private function warnOnce(string $type, string $message, array $context): void
    {
        if (!isset($this->warned[$type])) {
            $this->warned[$type] = true;
            log()->warning($message, $context);
        }
    }
}
