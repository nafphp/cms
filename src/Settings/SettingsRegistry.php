<?php

declare(strict_types=1);

namespace Naf\CMS\Settings;

use LogicException;
use RuntimeException;

final class SettingsRegistry
{
    /** @var array<string, SettingSection> */
    private array $sections = [];

    public function add(SettingSection $section, bool $replace = false): self
    {
        return $this->addSection($section, $replace);
    }

    public function addSection(SettingSection $section, bool $replace = false): self
    {
        if (isset($this->sections[$section->id()]) && !$replace) {
            throw new LogicException(sprintf('Setting section "%s" is already registered.', $section->id()));
        }

        $this->sections[$section->id()] = $section;

        return $this;
    }

    /** @param iterable<SettingSection> $sections */
    public function addMany(iterable $sections, bool $replace = false): self
    {
        foreach ($sections as $section) {
            $this->addSection($section, $replace);
        }

        return $this;
    }

    public function addField(string $sectionId, SettingField $field, bool $replace = false): self
    {
        $section = $this->sections[$sectionId] ?? null;
        if (!$section instanceof SettingSection) {
            throw new RuntimeException(sprintf('Setting section "%s" is not registered.', $sectionId));
        }

        $section->addField($field, $replace);

        return $this;
    }

    public function field(string $key): ?SettingField
    {
        foreach ($this->sections as $section) {
            if (isset($section->fields()[$key])) {
                return $section->fields()[$key];
            }
        }

        return null;
    }

    /** @return array<string, SettingSection> */
    public function sections(?string $group = null): array
    {
        $sections = $this->sections;
        if ($group !== null) {
            $sections = array_filter(
                $sections,
                static fn(SettingSection $section): bool => $section->group() === $group,
            );
        }

        uasort(
            $sections,
            static fn(SettingSection $left, SettingSection $right): int => [$left->order(), $left->label()] <=> [$right->order(), $right->label()],
        );

        return $sections;
    }

    /** @return array<string, array{label: string, sections: array<string, SettingSection>}> */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->sections() as $section) {
            $group = $section->group();
            $groups[$group] ??= [
                'label'    => $group === 'ai' ? 'AI' : ucfirst(str_replace(['-', '_'], ' ', $group)),
                'sections' => [],
            ];
            $groups[$group]['sections'][$section->id()] = $section;
        }

        return $groups;
    }

    /** @return array<string, SettingField> */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->sections as $section) {
            foreach ($section->fields() as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
