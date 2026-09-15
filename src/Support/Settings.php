<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use Naf\CMS\Contracts\TranslationServiceInterface;
use Naf\CMS\Repositories\SettingRepository;
use Naf\CMS\Settings\SettingField;
use Naf\CMS\Settings\SettingSection;
use Naf\CMS\Settings\SettingsRegistry;
use Naf\I18n\Core\Translator;
use Throwable;

use function Naf\app;
use function Naf\CMS\cms;
use function Naf\config;
use function Naf\request;

final class Settings
{
    private ?SettingRepository $settings = null;
    private ?SettingsRegistry $registry  = null;
    /** @var array<string, string|null> */
    private array $cache = [];

    public function name(): string
    {
        $configured = trim((string) $this->value('app.name', 'NafCMS'));

        return $configured !== '' ? $configured : 'NafCMS';
    }

    private function registry(): SettingsRegistry
    {
        if ($this->registry === null) {
            $this->registry = new SettingsRegistry();
        }

        return $this->registry;
    }

    public function add(SettingSection $section, bool $replace = false): self
    {
        $this->registry()->add($section, $replace);

        return $this;
    }

    /**
     * @param iterable<SettingSection> $sections
     */
    public function addMany(iterable $sections, bool $replace = false): self
    {
        $this->registry()->addMany($sections, $replace);

        return $this;
    }

    public function addSection(SettingSection $section, bool $replace = false): self
    {
        $this->registry()->addSection($section, $replace);

        return $this;
    }

    public function addField(string $sectionId, SettingField $field, bool $replace = false): self
    {
        $this->registry()->addField($sectionId, $field, $replace);

        return $this;
    }

    public function field(string $key): ?SettingField
    {
        return $this->registry()->field($key);
    }

    /**
     * @return array<string, SettingSection>
     */
    public function sections(?string $group = null): array
    {
        return $this->registry()->sections($group);
    }

    /**
     * @return array<string, array{label: string, sections: array<string, SettingSection>}>
     */
    public function groups(): array
    {
        return $this->registry()->groups();
    }

    /**
     * @return array<string, SettingField>
     */
    public function fields(): array
    {
        return $this->registry()->fields();
    }

    public function shortName(): string
    {
        $configured = trim((string) $this->value('app.short_name', ''));
        if ($configured !== '') {
            return strtoupper(substr($configured, 0, 3));
        }

        $name  = preg_replace('/[^a-z0-9]+/i', ' ', $this->name()) ?: 'NafCMS';
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

        return cms()->languages()->enabled($configured);
    }

    /**
     * The language the current visitor should be served in.
     *
     * Single source of truth: query parameter, then the translator, then the
     * configured site language. Everything that needs "which language are we
     * in" must go through here instead of resolving it again, which is how
     * page lookup and article lookup drifted apart.
     */
    public function currentLanguage(): string
    {
        $requested = $this->requestedLanguage();
        if ($requested !== null) {
            return $requested;
        }

        try {
            /** @var Translator $translator */
            $translator         = app()->container()->get(Translator::class);
            $translatorLanguage = strtolower(trim((string) ($translator->getLanguage() ?? '')));
            if ($translatorLanguage !== '' && in_array($translatorLanguage, $this->languages(), true)) {
                return $translatorLanguage;
            }
        } catch (Throwable) {
        }

        return $this->language();
    }

    /**
     * The ?lang= override, when there is an HTTP request and the value is a
     * language this installation actually serves.
     *
     * Outside a request (CLI, queue worker) there is nothing to read, which
     * must not be an error: callers fall back to the translator.
     */
    private function requestedLanguage(): ?string
    {
        try {
            $requested = strtolower(trim((string) (request()->getQueryParams()['lang'] ?? '')));
        } catch (Throwable) {
            return null;
        }

        return $requested !== '' && in_array($requested, $this->languages(), true) ? $requested : null;
    }

    public function language(): string
    {
        $configured = strtolower(trim((string) $this->value('language', 'en')));

        return in_array($configured, $this->languages(), true) ? $configured : $this->fallbackLanguage();
    }

    public function fallbackLanguage(): string
    {
        $configured = strtolower(trim((string) $this->value('fallback_language', 'en')));
        if (cms()->languages()->normalize($configured) !== null) {
            return $configured;
        }

        return 'en';
    }

    public function languageUrl(string $language): string
    {
        $query         = request()->getQueryParams();
        $query['lang'] = strtolower(trim($language));
        $queryString   = http_build_query($query);
        $path          = request()->getUri()->getPath();

        return $queryString === '' ? $path : $path . '?' . $queryString;
    }

    public function translationFileExists(string $language): bool
    {
        $language = strtolower(trim($language));
        if (cms()->languages()->normalize($language) === null) {
            return false;
        }

        return app()->container()->get(TranslationServiceInterface::class)->hasLanguage($language);
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
