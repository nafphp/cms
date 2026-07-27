<?php

declare(strict_types=1);

namespace src\Localization;

use src\Services\Settings\TranslationFileService;
use NixPHP\I18n\Core\Translator;
use NixPHP\I18n\Support\Language;
use Stringable;
use function NixPHP\config;

final class RuntimeTranslator extends Translator
{
    private ?string $runtimeLanguage;
    /** @var array<string, string> */
    private array $runtimeData = [];

    public function __construct(
        ?string $language = null,
        private readonly ?TranslationFileService $translations = null,
    ) {
        $this->runtimeLanguage = $language !== null ? Language::normalize($language) : null;
        $this->reload();
    }

    public function reload(): void
    {
        $this->runtimeLanguage ??= Language::normalize(
            (string) (config('language') ?? config('fallback_language', Language::EN)),
        );
        $this->runtimeData = ($this->translations ?? new TranslationFileService())->entries($this->runtimeLanguage);
    }

    public function translate(string $key, array $params = []): string
    {
        $result = $this->runtimeData[$key] ?? $key;
        $replacements = [];

        foreach ($params as $name => $value) {
            if (is_scalar($value) || $value instanceof Stringable || $value === null) {
                $replacements[':' . $name] = (string) $value;
            }
        }

        return $replacements === [] ? $result : strtr($result, $replacements);
    }

    public function getLanguage(): ?string
    {
        return $this->runtimeLanguage;
    }

    public function setLanguage(string $lang): void
    {
        $this->runtimeLanguage = Language::normalize($lang);
        $this->reload();
    }
}
