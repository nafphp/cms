<?php

declare(strict_types=1);

namespace NixCMS\Services\Settings;

use NixCMS\Repositories\SettingRepository;
use NixCMS\Settings\SettingField;
use NixCMS\Settings\SettingSection;
use function NixPHP\config;

final class SettingsService
{
    public function __construct(
        private readonly ?SettingRepository $repository = null,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(?string $group = null): array
    {
        $stored = $this->repository()->all();
        $sections = [];

        foreach (\settings()->sections($group) as $section) {
            $sections[] = $this->sectionPayload($section, $stored);
        }

        return $sections;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, errors: array<string, list<string>>}
     */
    public function save(array $payload): array
    {
        $values = [];
        $errors = [];
        $oldValues = $this->currentValues();

        foreach (\settings()->fields() as $key => $field) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $this->normalizeValue($field, $payload[$key] ?? null);
            if ($value === null) {
                $errors[$key][] = 'Invalid setting value.';
                continue;
            }

            $values[$key] = $value;
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $saved = $this->repository()->saveMany($values);
        if ($saved) {
            $this->auditChanges($oldValues, $values);
        }

        return ['success' => $saved, 'errors' => []];
    }

    /** @param array<string, string> $stored */
    private function sectionPayload(SettingSection $section, array $stored): array
    {
        $fields = [];
        foreach ($section->fields() as $field) {
            $fields[] = [
                'key' => $field->key(),
                'label' => $field->label(),
                'type' => $field->type(),
                'description' => $field->description(),
                'value' => $stored[$field->key()] ?? $this->defaultValue($field),
                'source' => $this->source($field, $stored),
                'options' => $field->options(),
            ];
        }

        return [
            'id' => $section->id(),
            'label' => $section->label(),
            'description' => $section->description(),
            'group' => $section->group(),
            'icon' => $section->icon(),
            'order' => $section->order(),
            'permission' => $section->permission(),
            'view' => $section->view(),
            'fields' => $fields,
        ];
    }

    private function normalizeValue(SettingField $field, mixed $value): ?string
    {
        if ($field->type() === 'multiselect') {
            $values = is_array($value) ? $value : preg_split('/[,\s]+/', (string) $value);
            $selected = array_values(array_unique(array_filter(array_map(
                static fn(mixed $entry): string => strtolower(trim((string) $entry)),
                $values ?: [],
            ))));
            $selected = array_values(array_filter(
                $selected,
                static fn(string $entry): bool => array_key_exists($entry, $field->options()),
            ));

            return $selected !== [] ? implode(',', $selected) : null;
        }

        $value = trim((string) $value);

        if ($field->type() === 'select') {
            return array_key_exists($value, $field->options()) ? $value : null;
        }

        if ($field->key() === 'cms.languages') {
            $languages = array_values(array_unique(array_filter(array_map(
                static fn(string $language): string => strtolower(trim($language)),
                preg_split('/[,\s]+/', $value) ?: [],
            ))));

            return $languages !== [] ? implode(',', $languages) : null;
        }

        if (in_array($field->key(), ['app.name', 'app.short_name'], true) && $value === '') {
            return null;
        }

        return $value;
    }

    private function defaultValue(SettingField $field): string
    {
        $configured = config(str_replace('.', ':', $field->key()));
        if (is_array($configured)) {
            return implode(',', array_map('strval', $configured));
        }

        if ($configured !== null && !is_object($configured)) {
            return (string) $configured;
        }

        $default = $field->defaultValue();
        if (is_array($default)) {
            return implode(',', array_map('strval', $default));
        }

        return (string) ($default ?? '');
    }

    /** @param array<string, string> $stored */
    private function source(SettingField $field, array $stored): string
    {
        if (array_key_exists($field->key(), $stored)) {
            return 'database';
        }

        $missing = new \stdClass();
        $configured = config(str_replace('.', ':', $field->key()), $missing);
        if ($configured !== $missing) {
            return 'config';
        }

        return 'default';
    }

    /** @return array<string, string> */
    private function currentValues(): array
    {
        $stored = $this->repository()->all();
        $values = [];

        foreach (\settings()->fields() as $key => $field) {
            $values[$key] = $stored[$key] ?? $this->defaultValue($field);
        }

        return $values;
    }

    /** @param array<string, string> $oldValues @param array<string, string> $newValues */
    private function auditChanges(array $oldValues, array $newValues): void
    {
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }

            \audit('setting.updated', 'setting', $key, $oldValue, $newValue);
        }
    }

    private function repository(): SettingRepository
    {
        return $this->repository ?? new SettingRepository();
    }
}
