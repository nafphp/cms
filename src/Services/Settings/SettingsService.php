<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Settings;

use Closure;
use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Repositories\SettingRepository;
use Naf\CMS\Settings\SettingField;
use Naf\CMS\Settings\SettingSection;
use stdClass;

use function Naf\CMS\audit_log;
use function Naf\CMS\cms;
use function Naf\CMS\user;
use function Naf\config;
use function Naf\I18n\t;

final class SettingsService implements SettingsServiceInterface
{
    public function __construct(
        private readonly ?SettingRepository $repository = null,
        ?callable $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker !== null ? Closure::fromCallable($permissionChecker) : null;
    }

    private readonly ?Closure $permissionChecker;

    /** @return array<int, array<string, mixed>> */
    public function sections(?string $group = null): array
    {
        $stored   = $this->repository()->all();
        $sections = [];

        foreach (cms()->settings()->sections($group) as $section) {
            if (!$this->canAccess($section)) {
                continue;
            }
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
        $values    = [];
        $errors    = [];
        $oldValues = $this->currentValues();

        foreach ($this->fieldsWithPermissions() as $key => [$field, $permission]) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            if (!$this->can($permission)) {
                $errors[$key][] = 'You do not have permission to update this setting.';
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

    /**
     * @param array<string, string> $stored
     * @return array<string, mixed>
     */
    private function sectionPayload(SettingSection $section, array $stored): array
    {
        $fields = [];
        foreach ($section->fields() as $field) {
            $fields[] = [
                'key'         => $field->key(),
                'label'       => t($field->label()),
                'type'        => $field->type(),
                'description' => $field->description() !== null ? t($field->description()) : null,
                'value'       => $stored[$field->key()] ?? $this->defaultValue($field),
                'source'      => $this->source($field, $stored),
                'options'     => $this->translatedOptions($field->options()),
            ];
        }

        return [
            'id'          => $section->id(),
            'label'       => t($section->label()),
            'description' => $section->description() !== null ? t($section->description()) : null,
            'group'       => $section->group(),
            'icon'        => $section->icon(),
            'order'       => $section->order(),
            'permission'  => $section->permission(),
            'view'        => $section->view(),
            'fields'      => $fields,
        ];
    }

    /**
     * @param array<string, string> $options
     * @return array<string, string>
     */
    private function translatedOptions(array $options): array
    {
        foreach ($options as $key => $label) {
            $options[$key] = t($label);
        }

        return $options;
    }

    private function normalizeValue(SettingField $field, mixed $value): ?string
    {
        if ($field->type() === 'multiselect') {
            $values   = is_array($value) ? $value : preg_split('/[,\s]+/', (string) $value);
            $selected = array_values(array_unique(array_filter(array_map(
                static fn(mixed $entry): ?string => cms()->languages()->normalize((string) $entry),
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
                static fn(string $language): ?string => cms()->languages()->normalize($language),
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

        $missing    = new stdClass();
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

        foreach (cms()->settings()->fields() as $key => $field) {
            $values[$key] = $stored[$key] ?? $this->defaultValue($field);
        }

        return $values;
    }

    /**
     * @param array<string, string> $oldValues
     * @param array<string, string> $newValues
     */
    private function auditChanges(array $oldValues, array $newValues): void
    {
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }

            audit_log(
                'setting.updated',
                'setting',
                $key,
                $this->auditValue($key, $oldValue),
                $this->auditValue($key, $newValue),
            );
        }
    }

    private function auditValue(string $key, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/(?:api[_-]?key|token|secret|password)$/i', $key) === 1 ? '[redacted]' : $value;
    }

    private function repository(): SettingRepository
    {
        return $this->repository ?? new SettingRepository();
    }

    private function canAccess(SettingSection $section): bool
    {
        return $this->can($section->permission());
    }

    private function can(?string $permission): bool
    {
        if ($permission === null || $permission === '') {
            $permission = Permission::SETTINGS_MANAGE->value;
        }

        if ($permission !== Permission::SETTINGS_MANAGE->value && !$this->can(Permission::SETTINGS_MANAGE->value)) {
            return false;
        }

        $case = Permission::tryFrom($permission);
        if (!$case instanceof Permission) {
            return false;
        }

        if ($this->permissionChecker instanceof Closure) {
            return (bool) ($this->permissionChecker)($case);
        }

        return user()?->can($case) === true;
    }

    /** @return array<string, array{0: SettingField, 1: string|null}> */
    private function fieldsWithPermissions(): array
    {
        $fields = [];
        foreach (cms()->settings()->sections() as $section) {
            foreach ($section->fields() as $field) {
                $fields[$field->key()] = [$field, $section->permission()];
            }
        }

        return $fields;
    }
}
