<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;

final class LanguagePackParityTest extends TestCase
{
    public function testGermanAndEnglishLanguagePacksExposeTheSameKeys(): void
    {
        $packs = [
            'de' => $this->loadLanguagePack('de'),
            'en' => $this->loadLanguagePack('en'),
        ];

        $keySets = array_map(fn(array $pack): array => $this->keys($pack), $packs);
        $allKeys = array_values(array_unique(array_merge(...array_values($keySets))));
        sort($allKeys);

        $missing = [];
        foreach ($keySets as $language => $keys) {
            $diff = array_values(array_diff($allKeys, $keys));
            if ($diff !== []) {
                $missing[$language] = $diff;
            }
        }

        self::assertSame([], $missing, $this->formatMissingKeys($missing));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLanguagePack(string $language): array
    {
        $file = BASE_PATH . '/src/Resources/lang/' . $language . '.json';
        $json = file_get_contents($file);
        self::assertIsString($json, sprintf('Language file "%s" must be readable.', $file));

        $decoded = json_decode($json, true);
        self::assertIsArray($decoded, sprintf(
            'Language file "%s" must contain valid JSON: %s',
            $file,
            json_last_error_msg(),
        ));

        return $decoded;
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private function keys(array $values, string $prefix = ''): array
    {
        $keys = [];
        foreach ($values as $key => $value) {
            $path   = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            $keys[] = $path;

            if (is_array($value)) {
                array_push($keys, ...$this->keys($value, $path));
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, list<string>> $missing
     */
    private function formatMissingKeys(array $missing): string
    {
        if ($missing === []) {
            return '';
        }

        $lines = ['Language pack key mismatch:'];
        foreach ($missing as $language => $keys) {
            $lines[] = sprintf('%s is missing: %s', $language, implode(', ', $keys));
        }

        return implode(PHP_EOL, $lines);
    }
}
