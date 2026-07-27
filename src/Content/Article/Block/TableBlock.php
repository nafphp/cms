<?php

declare(strict_types=1);

namespace src\Content\Article\Block;

use src\Content\Article\Block\AbstractBlock;

final class TableBlock extends AbstractBlock
{
    public function render(array $data): string
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
}
