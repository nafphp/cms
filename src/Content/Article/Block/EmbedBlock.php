<?php

declare(strict_types=1);

namespace src\Content\Article\Block;

use src\Security\UrlPolicy;
use src\Content\Article\Block\AbstractBlock;

final class EmbedBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        $embed = UrlPolicy::embed((string) ($data['embed'] ?? ''));
        if ($embed === null) {
            return '';
        }

        return '<iframe src="' . $this->escape($embed) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-forms allow-popups allow-same-origin allow-scripts" allowfullscreen></iframe>';
    }
}
