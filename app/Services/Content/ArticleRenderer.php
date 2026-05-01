<?php

declare(strict_types=1);

namespace Cms\Services\Content;

final class ArticleRenderer
{
    /**
     * @param array<string, mixed> $article
     */
    public function __construct(
        private readonly array $article,
    ) {
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

            if ($type === 'image' && $this->hideLeadImageInContent() && !$leadImageSkipped) {
                $leadImageSkipped = true;
                continue;
            }

            $html[] = $this->renderBlock($type, $data);
        }

        return implode("\n", array_filter($html));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderBlock(string $type, array $data): string
    {
        return match ($type) {
            'header' => $this->renderHeader($data),
            'list' => $this->renderList($data),
            'quote' => $this->renderQuote($data),
            'code' => '<pre><code>' . $this->escape((string) ($data['code'] ?? '')) . '</code></pre>',
            'table' => $this->renderTable($data),
            'image' => $this->renderImage($data),
            'embed' => $this->renderEmbed($data),
            default => '<p>' . $this->cleanInline((string) ($data['text'] ?? '')) . '</p>',
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderHeader(array $data): string
    {
        $level = max(2, min(4, (int) ($data['level'] ?? 2)));
        return sprintf('<h%d>%s</h%d>', $level, $this->cleanInline((string) ($data['text'] ?? '')), $level);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderList(array $data): string
    {
        $style = (string) ($data['style'] ?? 'unordered');
        $tag = $style === 'ordered' ? 'ol' : 'ul';
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $html = array_map(fn(mixed $item): string => '<li>' . $this->cleanInline((string) $item) . '</li>', $items);

        return '<' . $tag . '>' . implode('', $html) . '</' . $tag . '>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderQuote(array $data): string
    {
        $caption = trim((string) ($data['caption'] ?? ''));
        return '<blockquote><p>' . $this->cleanInline((string) ($data['text'] ?? '')) . '</p>'
            . ($caption !== '' ? '<cite>' . $this->escape($caption) . '</cite>' : '')
            . '</blockquote>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTable(array $data): string
    {
        $rows = is_array($data['content'] ?? null) ? $data['content'] : [];
        $html = '<table><tbody>';
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $this->cleanInline((string) $cell) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderImage(array $data): string
    {
        $file = is_array($data['file'] ?? null) ? $data['file'] : [];
        $url = trim((string) ($file['url'] ?? $data['url'] ?? ''));
        if ($url === '') {
            return '';
        }

        $caption = trim((string) ($data['caption'] ?? ''));
        return '<figure><img src="' . $this->escape($url) . '" alt="' . $this->escape($caption) . '">'
            . ($caption !== '' ? '<figcaption>' . $this->escape($caption) . '</figcaption>' : '')
            . '</figure>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderEmbed(array $data): string
    {
        $embed = trim((string) ($data['embed'] ?? ''));
        if ($embed === '') {
            return '';
        }

        return '<iframe src="' . $this->escape($embed) . '" loading="lazy" allowfullscreen></iframe>';
    }

    private function cleanInline(string $html): string
    {
        return strip_tags($html, '<b><strong><i><em><u><a><mark><code><br>');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
