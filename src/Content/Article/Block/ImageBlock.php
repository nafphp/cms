<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Article\Block;

use Naf\CMS\Security\UrlPolicy;
use Naf\CMS\Content\Article\Block\AbstractBlock;

final class ImageBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        $file = is_array($data['file'] ?? null) ? $data['file'] : [];
        $url = UrlPolicy::media((string) ($file['url'] ?? $data['url'] ?? ''));
        if ($url === null) {
            return '';
        }

        $caption = trim((string) ($data['caption'] ?? ''));

        return '<figure><img src="' . $this->escape($url) . '" alt="' . $this->escape($caption) . '">'
            . ($caption !== '' ? '<figcaption>' . $this->escape($caption) . '</figcaption>' : '')
            . '</figure>';
    }
}
