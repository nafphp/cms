<?php

declare(strict_types=1);

namespace NixCMS\Support;

use NixCMS\Enums\Language;
use NixCMS\Repositories\SettingRepository;
use NixPHP\I18n\Core\Translator;
use function NixPHP\app;
use function NixPHP\config;
use function NixPHP\request;

final class Settings
{
    private ?SettingRepository $settings = null;
    /** @var array<string, string|null> */
    private array $cache = [];

    public function name(): string
    {
        $configured = trim((string) $this->value('app.name', 'NixCMS'));
        return $configured !== '' ? $configured : 'NixCMS';
    }

    public function shortName(): string
    {
        $configured = trim((string) $this->value('app.short_name', ''));
        if ($configured !== '') {
            return strtoupper(substr($configured, 0, 3));
        }

        $name = preg_replace('/[^a-z0-9]+/i', ' ', $this->name()) ?: 'NixCMS';
        $parts = array_values(array_filter(explode(' ', trim($name))));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }

        return strtoupper(substr($parts[0] ?? 'NX', 0, 2));
    }

    public function logoText(): string
    {
        $configured = trim((string) $this->value('branding.logoText', ''));
        return $configured !== '' ? $configured : $this->name();
    }

    public function logoImage(): string
    {
        return trim((string) $this->value('branding.logoImage', ''));
    }

    public function pageTitle(string $title = ''): string
    {
        $title = trim($title);
        if ($title === $this->name()) {
            return $this->name();
        }

        return $title !== '' ? $title . ' | ' . $this->name() : $this->name();
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        $configured = $this->value('cms.languages', []);
        if (is_string($configured)) {
            $configured = preg_split('/[,\s]+/', $configured) ?: [];
        }

        $languages = is_array($configured) ? $configured : [];
        $languages = array_values(array_filter(array_map(
            static fn(mixed $language): string => strtolower(trim((string) $language)),
            $languages
        )));

        if ($languages === []) {
            $languages = array_map(static fn(Language $language): string => $language->value, Language::cases());
        }

        return array_values(array_unique($languages));
    }

    public function currentLanguage(): string
    {
        $queryLang = strtolower(trim((string) (request()->getQueryParams()['lang'] ?? '')));
        if ($queryLang !== '' && in_array($queryLang, $this->languages(), true)) {
            return $queryLang;
        }

        try {
            /** @var Translator $translator */
            $translator = app()->container()->get(Translator::class);
            $translatorLanguage = strtolower(trim((string) ($translator->getLanguage() ?? '')));
            if ($translatorLanguage !== '' && in_array($translatorLanguage, $this->languages(), true)) {
                return $translatorLanguage;
            }
        } catch (\Throwable) {
        }

        return $this->language();
    }

    public function language(): string
    {
        $configured = strtolower(trim((string) $this->value('language', 'en')));
        return in_array($configured, $this->languages(), true) ? $configured : $this->fallbackLanguage();
    }

    public function fallbackLanguage(): string
    {
        $configured = strtolower(trim((string) $this->value('fallback_language', 'en')));
        if (preg_match('/^[a-z]{2,3}$/', $configured) === 1) {
            return $configured;
        }

        return 'en';
    }

    public function languageUrl(string $language): string
    {
        $query = request()->getQueryParams();
        $query['lang'] = strtolower(trim($language));
        $queryString = http_build_query($query);
        $path = request()->getUri()->getPath();

        return $queryString === '' ? $path : $path . '?' . $queryString;
    }

    public function translationFileExists(string $language): bool
    {
        $language = strtolower(trim($language));
        if (preg_match('/^[a-z]{2,3}$/', $language) !== 1) {
            return false;
        }

        $path = BASE_PATH . (string) config('app:translationPath', '/app/Resources/lang');
        return is_file($path . '/' . $language . '.json');
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $stored = $this->setting($key);
        if ($stored !== null) {
            return $stored;
        }

        return config($this->configKey($key), $default);
    }

    private function setting(string $key): ?string
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $this->settings ??= new SettingRepository();
        return $this->cache[$key] = $this->settings->value($key);
    }

    private function configKey(string $key): string
    {
        return str_replace('.', ':', trim($key));
    }
}
