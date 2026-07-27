<?php

declare(strict_types=1);

namespace src\Services\Form;

use function NixPHP\I18n\t;

final class FormTextTranslator
{
    public function translate(?string $key, string $fallback = ''): string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return $fallback;
        }

        $translated = t($key);
        if ($translated === $key && $fallback !== '') {
            return $fallback;
        }

        return $translated;
    }

    /** @param array<string, mixed> $settings */
    public function setting(array $settings, string $name, string $fallback = ''): string
    {
        return $this->translate(
            is_string($settings[$name . 'Key'] ?? null) ? $settings[$name . 'Key'] : null,
            (string) ($settings[$name] ?? $fallback)
        );
    }

    /** @param array<string, mixed> $field */
    public function fieldLabel(array $field): string
    {
        return $this->translate(
            is_string($field['labelKey'] ?? null) ? $field['labelKey'] : null,
            (string) ($field['label'] ?? $field['name'] ?? '')
        );
    }

    /** @param array<string, mixed> $field */
    public function fieldPlaceholder(array $field): string
    {
        return $this->translate(
            is_string($field['placeholderKey'] ?? null) ? $field['placeholderKey'] : null,
            (string) ($field['placeholder'] ?? '')
        );
    }
}
