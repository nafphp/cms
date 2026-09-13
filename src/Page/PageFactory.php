<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\CMS\Content\BlockViewRegistry;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\CustomLayoutNormalizer;
use Naf\CMS\Page\Layout;
use Naf\CMS\Page\Page;
use Naf\CMS\Page\PageBuilderPayloadNormalizer;
use Naf\CMS\Page\Template;
use function Naf\log;
use function Naf\CMS\cms;

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
        $layout = Layout::create(Template::create((string) ($definition['view'] ?? 'page.templates.default'))
            ->setParent((string) ($row['layout'] ?? $definition['layout'] ?? 'layouts.page'))
            ->addStylesheet('/css/nafcms.css')
            ->addScript('/js/page-forms.js', ['type' => 'module'])
            ->addScript('/js/admin/diagrams.js', ['type' => 'module'])
            ->addBodyClass('min-h-screen bg-[var(--page-bg)] text-[var(--page-text)]'));

        $storedBlocks = is_array($row['blocks'] ?? null) ? $row['blocks'] : [];
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        $definitionBlocks = is_array($definition['blocks'] ?? null) ? $definition['blocks'] : [];
        $layoutErrors = [];
        if (array_key_exists('custom_layout', $meta)) {
            $definitionBlocks = (new CustomLayoutNormalizer())->normalize(
                is_array($meta['custom_layout'] ?? null) ? $meta['custom_layout'] : [],
                $layoutErrors,
            );
        }

        $blockErrors = [];
        $storedBlocks = (new PageBuilderPayloadNormalizer())->normalize($storedBlocks, $definitionBlocks, $blockErrors);

        // Rejected blocks are dropped from the page, so the page renders as an
        // empty region instead of failing. Without this the loss is invisible.
        $this->reportNormalizationErrors($row, [...$layoutErrors, ...$blockErrors]);

        foreach ($definitionBlocks as $block) {
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
                $layout->addBlock($id, 'page.blocks.empty', $this->blockData($id, $block, [
                    'content' => [],
                ]));
                continue;
            }

            $layout->addBlock($id, $this->blockViews()->viewFor($type), $this->blockData($id, $block, [
                'label' => (string) ($content['label'] ?? $block['label'] ?? $id),
                'preview' => (string) ($block['preview'] ?? $type),
                'content' => $content,
            ]));
        }

        $page = Page::create('db.page.' . (int) ($row['id'] ?? 0))
            ->setPath((string) ($row['path'] ?? '/'))
            ->setTitle((string) ($row['title'] ?? 'Page'))
            ->setLanguage((string) ($row['language'] ?? ''))
            ->setTheme((string) ($row['theme_id'] ?? $row['themeId'] ?? ''))
            ->setMeta(is_array($row['meta'] ?? null) ? $row['meta'] : [])
            ->setLayout($layout);

        if ((string) ($row['status'] ?? 'draft') !== 'published') {
            $page->requirePermission(Permission::PAGES_VIEW);
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $errors
     */
    private function reportNormalizationErrors(array $row, array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $errors = array_values(array_map('strval', $errors));

        // The reasons go into the message, not only the context: loggers that
        // render the message alone would otherwise report a loss without
        // saying what was lost.
        $reasons = implode('; ', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $reasons .= sprintf(' (+%d more)', count($errors) - 5);
        }

        log()->warning(
            sprintf(
                'PageFactory dropped stored page content for page %d (%s, template "%s"): %s',
                (int) ($row['id'] ?? 0),
                (string) ($row['path'] ?? ''),
                (string) ($row['template_id'] ?? ''),
                $reasons,
            ),
            [
                'page_id' => (int) ($row['id'] ?? 0),
                'path' => (string) ($row['path'] ?? ''),
                'template_id' => (string) ($row['template_id'] ?? ''),
                'errors' => $errors,
            ],
        );
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function blockData(string $id, array $block, array $data = []): array
    {
        return [
            'label' => (string) ($block['label'] ?? $id),
            'region' => (string) ($block['region'] ?? 'main'),
            'size' => (string) ($block['size'] ?? 'medium'),
            'position' => (string) ($block['position'] ?? 'stretch'),
            'maxHeight' => (string) ($block['maxHeight'] ?? 'none'),
            'overflow' => (string) ($block['overflow'] ?? 'visible'),
            'surface' => (string) ($block['surface'] ?? 'none'),
            ...$data,
        ];
    }

    private function blockViews(): BlockViewRegistry
    {
        if ($this->blockViews !== null) {
            return $this->blockViews;
        }

        return $this->blockViews = cms()->blocks();
    }

    /**
     * The block type an empty slot falls back to.
     *
     * Shipped templates declare this on the block itself. The id-based match
     * below only covers custom layouts stored before that field existed, where
     * dropping it would turn a saved page's chrome into empty regions.
     *
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
