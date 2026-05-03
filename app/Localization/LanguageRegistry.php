<?php

declare(strict_types=1);

namespace NixCMS\Localization;

use NixCMS\Enums\Language;
use function NixPHP\config;
use function NixPHP\I18n\t;

final class LanguageRegistry
{
    public function __construct(private ?string $translationPath = null)
    {
    }

    /**
     * @param mixed $configured
     * @return list<string>
     */
    public function enabled(mixed $configured = null): array
    {
        $languages = $this->normalizeList($configured ?? $this->config('cms:languages', []));

        return $languages !== [] ? $languages : $this->defaults();
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        $languages = [
            ...$this->defaults(),
            ...$this->normalizeList($this->config('cms:languages', [])),
            ...$this->translationFileLanguages(),
        ];

        $languages = array_values(array_unique($languages));
        sort($languages);

        return $languages;
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->available() as $language) {
            $options[$language] = $this->label($language);
        }

        return $options;
    }

    public function label(string $language): string
    {
        $language = $this->normalize($language) ?? '';
        if ($language === '') {
            return '';
        }

        $translated = t($language);
        return $translated !== $language ? $translated : strtoupper($language);
    }

    public function isKnown(string $language): bool
    {
        $language = $this->normalize($language);
        return $language !== null && in_array($language, $this->available(), true);
    }

    public function normalize(string $language): ?string
    {
        $language = strtolower(trim($language));
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,4})?$/', $language) !== 1) {
            return null;
        }

        return strlen($language) <= 8 ? $language : null;
    }

    /**
     * @return list<string>
     */
    private function defaults(): array
    {
        return array_map(static fn(Language $language): string => $language->value, Language::cases());
    }

    /**
     * @return list<string>
     */
    private function translationFileLanguages(): array
    {
        $directory = $this->translationPath ?? BASE_PATH . (string) $this->config('app:translationPath', '/app/Resources/lang');
        $files = glob($directory . '/*.json') ?: [];

        $languages = [];
        foreach ($files as $file) {
            if (!$this->isReadableLanguagePack($file)) {
                continue;
            }

            $languages[] = basename($file, '.json');
        }

        return $this->normalizeList($languages);
    }

    private function isReadableLanguagePack(string $file): bool
    {
        $json = file_get_contents($file);
        if (!is_string($json)) {
            return false;
        }

        return is_array(json_decode($json, true));
    }

    /**
     * @param mixed $languages
     * @return list<string>
     */
    private function normalizeList(mixed $languages): array
    {
        if (is_string($languages)) {
            $languages = preg_split('/[,\s]+/', $languages) ?: [];
        }

        if (!is_array($languages)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn(mixed $language): ?string => $this->normalize((string) $language),
            $languages,
        ))));
    }

    private function config(string $key, mixed $default = null): mixed
    {
        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
