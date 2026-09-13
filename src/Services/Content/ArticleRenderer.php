<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Content;

use Naf\CMS\Content\Article\Block\ParagraphBlock;
use Naf\CMS\Content\Article\BlockRegistry;

final class ArticleRenderer
{
    private const LEAD_IMAGE_TYPE = 'image';

    private readonly BlockRegistry $registry;
    private readonly ParagraphBlock $fallback;

    /**
     * @param array<string, mixed> $article
     */
    public function __construct(
        private readonly array $article,
        ?BlockRegistry $registry = null,
    ) {
        $this->registry = $registry ?? new BlockRegistry();
        $this->fallback = new ParagraphBlock();
    }

    public function title(): string
    {
        return (string) ($this->article['title'] ?? '');
    }

    public function teaser(): string
    {
        return (string) ($this->article['teaser'] ?? '');
    }

    public function slug(): string
    {
        return (string) ($this->article['slug'] ?? '');
    }

    public function language(): string
    {
        return (string) ($this->article['language'] ?? '');
    }

    public function status(): string
    {
        return (string) ($this->article['status'] ?? '');
    }

    public function articleId(): int
    {
        return (int) ($this->article['id'] ?? 0);
    }

    public function versionId(): int
    {
        return (int) ($this->article['article_version_id'] ?? 0);
    }

    public function publishedAt(): string
    {
        return (string) ($this->article['publishedAt'] ?? '');
    }

    public function author(): string
    {
        return (string) ($this->article['author'] ?? '');
    }

    public function categoryId(): ?int
    {
        $categoryId = (int) ($this->article['category_id'] ?? 0);
        return $categoryId > 0 ? $categoryId : null;
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = $this->article['tags'] ?? [];
        if (!is_array($tags)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $tag): string => trim((string) $tag),
            $tags
        )));
    }

    public function showTeaser(): bool
    {
        return (int) ($this->article['show_teaser'] ?? 0) === 1;
    }

    public function hideLeadImageInContent(): bool
    {
        return (int) ($this->article['hide_lead_image_in_content'] ?? 1) === 1;
    }

    public function html(): string
    {
        $content = json_decode((string) ($this->article['content'] ?? ''), true);
        $blocks = is_array($content) && isset($content['blocks']) && is_array($content['blocks'])
            ? $content['blocks']
            : [];

        $html = [];
        $leadImageSkipped = false;

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if ($type === self::LEAD_IMAGE_TYPE && $this->hideLeadImageInContent() && !$leadImageSkipped) {
                $leadImageSkipped = true;
                continue;
            }

            $renderer = $this->registry->resolve($type) ?? $this->fallback;
            $html[] = $renderer->render($data);
        }

        return implode("\n", array_filter($html));
    }
}
