<?php

declare(strict_types=1);

namespace NixCMS\Content\Article\Block;

final class CodeBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<pre><code>' . $this->escape((string) ($data['code'] ?? '')) . '</code></pre>';
    }
}
