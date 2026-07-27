<?php

declare(strict_types=1);

namespace src\Services\Settings;

use src\Support\AtomicFileWriter;
use src\Support\RuntimePaths;
use function NixCMS\cms;
use function NixCMS\audit;

final class TranslationFileService
{
    private readonly string $overrideDirectory;
    private readonly string $defaultDirectory;
    private readonly AtomicFileWriter $writer;

    public function __construct(
        ?string $overrideDirectory = null,
        ?string $defaultDirectory = null,
        ?AtomicFileWriter $writer = null,
    ) {
        $this->overrideDirectory = rtrim($overrideDirectory ?? RuntimePaths::translations(), '/\\');
        $this->defaultDirectory = rtrim($defaultDirectory ?? RuntimePaths::packageTranslations(), '/\\');
        $this->writer = $writer ?? new AtomicFileWriter();
    }

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

    public function hasLanguage(string $language): bool
    {
        $language = $this->normalizeLanguage($language);
        return $language !== '' && (
            is_file($this->overrideFile($language))
            || is_file($this->defaultFile($language))
        );
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

        $file = $this->overrideFile($language);
        try {
            $this->writer->write($file, $json . PHP_EOL);
        } catch (\Throwable) {
            return ['success' => false, 'errors' => ['file' => ['Translation file is not writable.']], 'changed' => 0];
        }

        return ['success' => true, 'errors' => [], 'changed' => $this->auditChanges($language, $old, $new, $file)];
    }

    /** @return array<string, string> */
    private function read(string $language): array
    {
        $language = $this->normalizeLanguage($language);
        if ($language === '') {
            return [];
        }

        return array_replace(
            $this->readFile($this->defaultFile($language)),
            $this->readFile($this->overrideFile($language)),
        );
    }

    /** @return array<string, string> */
    private function readFile(string $file): array
    {
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

    private function overrideFile(string $language): string
    {
        return $this->overrideDirectory . '/' . $language . '.json';
    }

    private function defaultFile(string $language): string
    {
        return $this->defaultDirectory . '/' . $language . '.json';
    }

    private function normalizeLanguage(string $language): string
    {
        return cms()->languages()->normalize($language) ?? '';
    }
}
