<?php

declare(strict_types=1);

namespace NixCMS\Stage\Targets;

use NixCMS\Content\Page\Block\BlockTypeInterface;
use NixCMS\Page\CustomLayoutNormalizer;
use NixCMS\Page\Layout;
use NixCMS\Repositories\PageLayoutRepository;
use NixCMS\Repositories\PageRepository;
use NixCMS\Stage\Stage;
use NixCMS\Stage\StageChange;
use NixCMS\Stage\StageCommitResult;
use NixCMS\Stage\StageTargetInterface;
use NixCMS\Stage\StageTargetResult;

final class PageStageTarget implements StageTargetInterface
{
    /** @param array<string, mixed> $page */
    public function __construct(
        private array $page,
        private ?PageRepository $pages = null,
    ) {}

    public function type(): string
    {
        return 'page';
    }

    public function id(): string
    {
        return (string) (int) ($this->page['id'] ?? 0);
    }

    public function snapshot(): array
    {
        return [
            'id' => (int) ($this->page['id'] ?? 0),
            'identifier' => (string) ($this->page['identifier'] ?? ''),
            'path' => (string) ($this->page['path'] ?? '/'),
            'title' => (string) ($this->page['title'] ?? ''),
            'language' => (string) ($this->page['language'] ?? ''),
            'status' => (string) ($this->page['status'] ?? 'draft'),
            'templateId' => (string) ($this->page['template_id'] ?? $this->page['templateId'] ?? ''),
            'themeId' => (string) ($this->page['theme_id'] ?? $this->page['themeId'] ?? ''),
            'layout' => (string) ($this->page['layout'] ?? 'layouts.page'),
            'blocks' => is_array($this->page['blocks'] ?? null) ? $this->page['blocks'] : [],
            'meta' => is_array($this->page['meta'] ?? null) ? $this->page['meta'] : [],
        ];
    }

    public function apply(StageChange $change, array $snapshot): StageTargetResult
    {
        return match ($change->type()) {
            'block.add' => $this->addBlock($snapshot, $change->payload()),
            'block.update' => $this->updateBlock($snapshot, $change->payload()),
            'block.remove' => $this->removeBlock($snapshot, $change->payload()),
            'template.set' => $this->setTemplate($snapshot, $change->payload()),
            'theme.set' => $this->setTheme($snapshot, $change->payload()),
            default => throw new \InvalidArgumentException(sprintf('Unsupported page stage change "%s".', $change->type())),
        };
    }

    public function commit(Stage $stage): StageCommitResult
    {
        $snapshot = $stage->workingSnapshot();
        $pageId = $this->pages()->save([
            'id' => (int) ($snapshot['id'] ?? 0),
            'identifier' => (string) ($snapshot['identifier'] ?? ''),
            'path' => (string) ($snapshot['path'] ?? ''),
            'title' => (string) ($snapshot['title'] ?? ''),
            'language' => (string) ($snapshot['language'] ?? ''),
            'status' => (string) ($snapshot['status'] ?? 'draft'),
            'template_id' => (string) ($snapshot['templateId'] ?? ''),
            'theme_id' => (string) ($snapshot['themeId'] ?? ''),
            'layout' => (string) ($snapshot['layout'] ?? 'layouts.page'),
            'blocks' => is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [],
            'meta' => is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [],
        ]);

        return StageCommitResult::success('Page stage committed.', [
            'pageId' => $pageId,
            'publishRequired' => true,
            'publishHint' => 'Commit saved the staged page state. Publish remains a separate page action.',
        ]);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $payload
     */
    private function addBlock(array $snapshot, array $payload): StageTargetResult
    {
        $blockTypeId = strtolower(trim((string) ($payload['blockType'] ?? $payload['block_type'] ?? '')));
        if ($blockTypeId === '') {
            throw new \InvalidArgumentException('block.add requires a blockType.');
        }

        $blockType = \cms()->blockTypes()->get($blockTypeId);
        if (!$blockType instanceof BlockTypeInterface) {
            throw new \InvalidArgumentException(sprintf('Block type "%s" is not registered.', $blockTypeId));
        }

        $region = $this->normalizeRegion((string) ($payload['region'] ?? 'main'));
        if (!in_array($region, $blockType->regions(), true)) {
            throw new \InvalidArgumentException(sprintf('Block type "%s" cannot be added to region "%s".', $blockTypeId, $region));
        }

        $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
        $layout = is_array($meta['custom_layout'] ?? null) ? $meta['custom_layout'] : [];
        $blockId = $this->uniqueBlockId($layout, $blockTypeId);
        $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
        $props = [...$blockType->defaults(), ...$config];

        $layout[] = [
            'id' => $blockId,
            'label' => $blockType->label(),
            'region' => $region,
            'size' => $this->defaultSize($blockTypeId, $region),
            'position' => 'stretch',
            'maxHeight' => 'none',
            'overflow' => 'visible',
            'surface' => 'none',
            'accepts' => [$blockTypeId],
            'defaultContentType' => $blockTypeId,
        ];

        $errors = [];
        $meta['custom_layout'] = (new CustomLayoutNormalizer())->normalize($layout, $errors);
        if ($errors !== []) {
            throw new \InvalidArgumentException($errors[0]);
        }

        $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];
        $blocks[$blockId] = [
            'type' => $blockTypeId,
            'label' => $blockType->label(),
            'previewTitle' => $blockType->previewTitle(),
            'props' => $props,
        ];

        $snapshot['templateId'] = $this->ensureCustomTemplateId((string) ($snapshot['templateId'] ?? 'custom'));
        $snapshot['meta'] = $meta;
        $snapshot['blocks'] = $blocks;

        return StageTargetResult::create($snapshot, [[
            'action' => 'pagebuilder.addBlock',
            'payload' => [
                'region' => $region,
                'blockType' => $blockTypeId,
                'config' => $config,
            ],
        ]]);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $payload
     */
    private function updateBlock(array $snapshot, array $payload): StageTargetResult
    {
        $blockId = trim((string) ($payload['blockId'] ?? $payload['block_id'] ?? ''));
        if ($blockId === '') {
            throw new \InvalidArgumentException('block.update requires a blockId.');
        }

        $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];
        if (!is_array($blocks[$blockId] ?? null)) {
            throw new \InvalidArgumentException(sprintf('Block "%s" is not part of this page snapshot.', $blockId));
        }

        $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
        $blocks[$blockId]['props'] = [
            ...(is_array($blocks[$blockId]['props'] ?? null) ? $blocks[$blockId]['props'] : []),
            ...$config,
        ];
        $snapshot['blocks'] = $blocks;

        return StageTargetResult::create($snapshot, [[
            'action' => 'pagebuilder.updateBlock',
            'payload' => [
                'blockId' => $blockId,
                'config' => $config,
            ],
        ]]);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $payload
     */
    private function removeBlock(array $snapshot, array $payload): StageTargetResult
    {
        $blockId = trim((string) ($payload['blockId'] ?? $payload['block_id'] ?? ''));
        if ($blockId === '') {
            throw new \InvalidArgumentException('block.remove requires a blockId.');
        }

        $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];
        unset($blocks[$blockId]);

        $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
        if (is_array($meta['custom_layout'] ?? null)) {
            $meta['custom_layout'] = array_values(array_filter(
                $meta['custom_layout'],
                static fn(mixed $slot): bool => is_array($slot) && (string) ($slot['id'] ?? '') !== $blockId
            ));
        }

        $snapshot['blocks'] = $blocks;
        $snapshot['meta'] = $meta;

        return StageTargetResult::create($snapshot);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $payload
     */
    private function setTemplate(array $snapshot, array $payload): StageTargetResult
    {
        $templateId = trim((string) ($payload['templateId'] ?? $payload['template_id'] ?? ''));
        if ($templateId === '') {
            throw new \InvalidArgumentException('template.set requires a templateId.');
        }

        $template = null;
        foreach ($this->templates() as $candidate) {
            if ((string) ($candidate['id'] ?? '') === $templateId) {
                $template = $candidate;
                break;
            }
        }

        if (!is_array($template)) {
            throw new \InvalidArgumentException(sprintf('Template "%s" is not registered.', $templateId));
        }

        $snapshot['templateId'] = $templateId;
        $snapshot['layout'] = (string) ($template['layout'] ?? $snapshot['layout'] ?? 'layouts.page');

        return StageTargetResult::create($snapshot);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $payload
     */
    private function setTheme(array $snapshot, array $payload): StageTargetResult
    {
        $themeId = trim((string) ($payload['themeId'] ?? $payload['theme_id'] ?? $payload['theme'] ?? ''));
        if ($themeId === '') {
            throw new \InvalidArgumentException('theme.set requires a themeId.');
        }

        if (\cms()->themes()->get($themeId) === null) {
            throw new \InvalidArgumentException(sprintf('Theme "%s" is not registered.', $themeId));
        }

        $snapshot['themeId'] = $themeId;

        return StageTargetResult::create($snapshot, [[
            'action' => 'pagebuilder.setTheme',
            'payload' => [
                'themeId' => $themeId,
            ],
        ]]);
    }

    /**
     * @param array<int, array<string, mixed>> $layout
     */
    private function uniqueBlockId(array $layout, string $blockType): string
    {
        $existing = array_flip(array_map(static fn(array $slot): string => (string) ($slot['id'] ?? ''), $layout));
        $base = 'ai_' . preg_replace('/[^a-z0-9]+/', '_', $blockType);
        $candidate = $base;
        $index = 2;

        while (isset($existing[$candidate])) {
            $candidate = $base . '_' . $index;
            $index++;
        }

        return $candidate;
    }

    private function normalizeRegion(string $region): string
    {
        $region = strtolower(trim($region));

        return match ($region) {
            '', 'content' => 'main',
            default => $region,
        };
    }

    private function defaultSize(string $blockType, string $region): string
    {
        if ($blockType === 'hero') {
            return 'hero';
        }
        if ($region === 'aside') {
            return 'aside';
        }
        if (in_array($region, ['header', 'footer'], true)) {
            return 'full';
        }

        return 'medium';
    }

    private function ensureCustomTemplateId(string $templateId): string
    {
        if ($templateId === '' || $templateId === 'custom' || str_starts_with($templateId, 'custom_layout.')) {
            return $templateId !== '' ? $templateId : 'custom';
        }

        foreach ($this->templates() as $template) {
            if ((string) ($template['id'] ?? '') === $templateId && (string) ($template['kind'] ?? 'static') === 'custom') {
                return $templateId;
            }
        }

        return 'custom';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $file = BASE_PATH . '/app/Resources/pages/page_templates.php';
        $definitions = is_file($file) ? require $file : [];

        return [
            ...Layout::definitions(is_array($definitions) ? $definitions : []),
            ...(new PageLayoutRepository())->templates(),
        ];
    }

    private function pages(): PageRepository
    {
        return $this->pages ??= new PageRepository();
    }
}
