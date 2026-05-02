<?php

declare(strict_types=1);

namespace NixCMS\Page;

use NixCMS\Content\BlockViewRegistry;
use NixCMS\Enums\Permission;

final class PageFactory
{
    public function __construct(private ?BlockViewRegistry $blockViews = null)
    {
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $definition
     */
    public function fromStoredRow(array $row, array $definition): Page
    {
        $template = Template::create((string) ($definition['view'] ?? 'page.templates.default'))
            ->setLayout((string) ($row['layout'] ?? $definition['layout'] ?? 'layouts.page'))
            ->addStylesheet('/css/nixcms.css')
            ->addScript('/js/page-forms.js', ['type' => 'module'])
            ->addBodyClass('min-h-screen bg-[var(--page-bg)] text-[var(--page-text)]');

        $storedBlocks = is_array($row['blocks'] ?? null) ? $row['blocks'] : [];
        foreach (is_array($definition['blocks'] ?? null) ? $definition['blocks'] : [] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $id = (string) ($block['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $content = is_array($storedBlocks[$id] ?? null) ? $storedBlocks[$id] : [];
            $type = trim((string) ($content['type'] ?? $this->defaultContentType($id, $block)));
            if ($type === '') {
                $template->addBlock($id, 'page.blocks.empty', [
                    'label' => (string) ($block['label'] ?? $id),
                    'size' => (string) ($block['size'] ?? 'medium'),
                    'content' => [],
                ]);
                continue;
            }

            $template->addBlock($id, $this->blockViews()->viewFor($type), [
                'label' => (string) ($content['label'] ?? $block['label'] ?? $id),
                'size' => (string) ($block['size'] ?? 'medium'),
                'preview' => (string) ($block['preview'] ?? $type),
                'content' => $content,
            ]);
        }

        $page = Page::create('db.page.' . (int) ($row['id'] ?? 0))
            ->setPath((string) ($row['path'] ?? '/'))
            ->setTitle((string) ($row['title'] ?? 'Page'))
            ->setLanguage((string) ($row['language'] ?? ''))
            ->setMeta(is_array($row['meta'] ?? null) ? $row['meta'] : [])
            ->setTemplate($template);

        if ((string) ($row['status'] ?? 'draft') !== 'published') {
            $page->requirePermission(Permission::PAGES_VIEW);
        }

        return $page;
    }

    private function blockViews(): BlockViewRegistry
    {
        return $this->blockViews ??= new BlockViewRegistry();
    }

    /**
     * @param array<string, mixed> $block
     */
    private function defaultContentType(string $id, array $block): string
    {
        if (isset($block['defaultContentType'])) {
            return (string) $block['defaultContentType'];
        }

        return match ($id) {
            'site_header' => 'header',
            'site_footer' => 'footer',
            default => '',
        };
    }
}
