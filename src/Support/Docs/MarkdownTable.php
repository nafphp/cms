<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support\Docs;

final class MarkdownTable
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    public static function render(array $headers, array $rows): string
    {
        if ($rows === []) {
            return '_No entries registered._';
        }

        $lines = [
            '| ' . implode(' | ', array_map(self::cell(...), $headers)) . ' |',
            '| ' . implode(' | ', array_fill(0, count($headers), '---')) . ' |',
        ];

        foreach ($rows as $row) {
            $cells = [];
            foreach (array_keys($headers) as $index) {
                $cells[] = self::cell($row[$index] ?? '');
            }

            $lines[] = '| ' . implode(' | ', $cells) . ' |';
        }

        return implode("\n", $lines);
    }

    private static function cell(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $value);
    }
}
