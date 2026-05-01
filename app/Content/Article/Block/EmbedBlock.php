<?php

declare(strict_types=1);

namespace NixCMS\Content\Article\Block;

final class EmbedBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        $embed = trim((string) ($data['embed'] ?? ''));
        if ($embed === '') {
            return '';
        }

        return '<iframe src="' . $this->escape($embed) . '" loading="lazy" allowfullscreen></iframe>';
    }
}
