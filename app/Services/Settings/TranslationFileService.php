<?php

declare(strict_types=1);

namespace NixCMS\Services\Settings;

use function NixPHP\config;
use function NixCMS\cms;
use function NixCMS\audit;

final class TranslationFileService
{
    /** @return array<int, array{code: string, label: string, active: bool}> */
    public function languages(string $activeLanguage = ''): array
    {
        $languages = array_values(array_unique([
            ...cms()->languages()->available(),
            ...cms()->settings()->languages(),
        ]));
        sort($languages);

        return array_map(static fn(string $language): array => [
            'code' => $language,
            'label' => cms()->languages()->label($language),
            'active' => $language === $activeLanguage,
        ], $languages);
    }

    /** @return array<string, string> */
    public function entries(string $language): array
    {
        $data = $this->read($language);
        ksort($data, SORT_NATURAL | SORT_FLAG_CASE);

        return $data;
    }

    /**
     * @param array<string, mixed> $entries
     * @return array{success: bool, errors: array<string, list<string>>, changed: int}
     */
    public function save(string $language, array $entries): array
    {
        $language = $this->normalizeLanguage($language);
        if ($language === '') {
            return ['success' => false, 'errors' => ['language' => ['Invalid language.']], 'changed' => 0];
        }

        $old = $this->read($language);
        $new = $this->normalizeEntries($entries);
        ksort($new, SORT_NATURAL | SORT_FLAG_CASE);
        $json = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return ['success' => false, 'errors' => ['entries' => ['Could not encode translations.']], 'changed' => 0];
        }

        $file = $this->file($language);
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return ['success' => false, 'errors' => ['file' => ['Translation directory is not writable.']], 'changed' => 0];
        }

        if (file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
            return ['success' => false, 'errors' => ['file' => ['Translation file is not writable.']], 'changed' => 0];
        }

        return ['success' => true, 'errors' => [], 'changed' => $this->auditChanges($language, $old, $new, $file)];
    }

    /** @return array<string, string> */
    private function read(string $language): array
    {
        $file = $this->file($this->normalizeLanguage($language));
        if (!is_file($file)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $entries = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && !is_array($value) && !is_object($value)) {
                $entries[$key] = (string) $value;
            }
        }

        return $entries;
    }

    /** @param array<string, mixed> $entries */
    private function normalizeEntries(array $entries): array
    {
        $normalized = [];
        foreach ($entries as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    /** @param array<string, string> $old @param array<string, string> $new */
    private function auditChanges(string $language, array $old, array $new, string $file): int
    {
        $changed = 0;
        $keys = array_values(array_unique([...array_keys($old), ...array_keys($new)]));
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }

            audit('translation.updated', 'translation', $language . '.' . $key, $oldValue, $newValue, [
                'language' => $language,
                'key' => $key,
                'file' => $file,
            ]);
            $changed++;
        }

        return $changed;
    }

    private function file(string $language): string
    {
        return $this->directory() . '/' . $language . '.json';
    }

    private function directory(): string
    {
        return BASE_PATH . (string) config('app:translationPath', '/app/Resources/lang');
    }

    private function normalizeLanguage(string $language): string
    {
        return cms()->languages()->normalize($language) ?? '';
    }
}
