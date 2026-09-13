<?php

declare(strict_types=1);

namespace Naf\CMS\Localization;

use Naf\CMS\Contracts\TranslationServiceInterface;
use Naf\I18n\Core\Translator;
use Naf\I18n\Support\Language;
use Stringable;
use function Naf\config;
use function Naf\app;

final class RuntimeTranslator extends Translator
{
    private ?string $runtimeLanguage;
    /** @var array<string, string> */
    private array $runtimeData = [];

    public function __construct(
        ?string $language = null,
        private readonly ?TranslationServiceInterface $translations = null,
    ) {
        $this->runtimeLanguage = $language !== null ? Language::normalize($language) : null;
        $this->reload();
    }

    public function reload(): void
    {
        $this->runtimeLanguage ??= Language::normalize(
            (string) (config('language') ?? config('fallback_language', Language::EN)),
        );
        $translations = $this->translations ?? app()->container()->get(TranslationServiceInterface::class);
        $this->runtimeData = $translations->entries($this->runtimeLanguage);
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
