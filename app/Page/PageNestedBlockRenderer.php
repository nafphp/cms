<?php

declare(strict_types=1);

namespace NixCMS\Page;

use NixCMS\Content\BlockViewRegistry;
use function NixPHP\View\s;
use function NixPHP\View\view;
use function NixCMS\cms;

final class PageNestedBlockRenderer
{
    private const MAX_DEPTH = 6;

    public function __construct(private ?BlockViewRegistry $blockViews = null)
    {
    }

    /**
     * @param array<int, mixed> $blocks
     * @param array<string, mixed> $context
     */
    public function renderMany(array $blocks, array $context = [], int $depth = 0): string
    {
        $html = [];

        foreach ($blocks as $block) {
            if (is_array($block)) {
                $html[] = $this->wrapChildOutput($this->render($block, $context, $depth), $block);
            }
        }

        return implode("\n", array_filter($html, static fn(string $output): bool => $output !== ''));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function render(array $payload, array $context = [], int $depth = 0): string
    {
        $type = trim((string) ($payload['type'] ?? ''));
        if ($type === '') {
            return '';
        }

        if ($depth >= self::MAX_DEPTH) {
            return view('page.blocks.placeholder', [
                ...$context,
                'content' => ['type' => 'placeholder', 'props' => []],
                'label' => 'Nested block depth limit reached',
                'preview' => 'block',
            ]);
        }

        return view($this->blockViews()->viewFor($type), [
            ...$context,
            'content' => $payload,
            'label' => (string) ($payload['label'] ?? $payload['previewTitle'] ?? $type),
            'preview' => (string) ($payload['previewTitle'] ?? $type),
            'nestedRenderer' => $this,
            'nestedDepth' => $depth + 1,
        ]);
    }

    private function blockViews(): BlockViewRegistry
    {
        if ($this->blockViews !== null) {
            return $this->blockViews;
        }

        return $this->blockViews = cms()->blocks();
    }

    /**
     * @param array<string, mixed> $block
     */
    private function wrapChildOutput(string $output, array $block): string
    {
        if ($output === '') {
            return '';
        }

        $props = is_array($block['props'] ?? null) ? $block['props'] : [];
        $style = (string) ($props['containerStyle'] ?? 'none');
        if (!in_array($style, ['border', 'soft', 'card', 'accent'], true)) {
            return $output;
        }

        return '<div class="page-layout-child page-layout-child--' . s($style) . '">' . $output . '</div>';
    }
}
