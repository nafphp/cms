<?php

declare(strict_types=1);

namespace NixCMS\Content\Article\Block;

final class ImageBlock extends AbstractBlock
{
    public function render(array $data): string
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
}
