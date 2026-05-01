<?php

declare(strict_types=1);

namespace NixCMS\Settings;

final class SettingSection
{
    /** @var array<string, SettingField> */
    private array $fields = [];
    private ?string $description = null;
    private string $group = 'general';
    private string $icon = 'settings';
    private int $order = 100;
    private ?string $permission = null;
    private ?string $view = null;

    private function __construct(
        private readonly string $id,
        private readonly string $label,
    ) {
    }

    public static function create(string $id, string $label): self
    {
        return new self($id, $label);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);
        return $this;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function setGroup(string $group): self
    {
        $group = trim($group);
        $this->group = $group !== '' ? $group : 'general';
        return $this;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): self
    {
        $icon = trim($icon);
        $this->icon = $icon !== '' ? $icon : 'settings';
        return $this;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function permission(): ?string
    {
        return $this->permission;
    }

    public function setPermission(string|\BackedEnum|null $permission): self
    {
        if ($permission instanceof \BackedEnum) {
            $permission = (string) $permission->value;
        }

        $permission = $permission !== null ? trim($permission) : '';
        $this->permission = $permission !== '' ? $permission : null;
        return $this;
    }

    public function view(): ?string
    {
        return $this->view;
    }

    public function setView(?string $view): self
    {
        $view = $view !== null ? trim($view) : '';
        $this->view = $view !== '' ? $view : null;
        return $this;
    }

    public function addField(SettingField $field, bool $replace = false): self
    {
        if (isset($this->fields[$field->key()]) && !$replace) {
            throw new \LogicException(sprintf('Setting field "%s" is already registered.', $field->key()));
        }

        $this->fields[$field->key()] = $field;
        return $this;
    }

    /** @param iterable<SettingField> $fields */
    public function addFields(iterable $fields, bool $replace = false): self
    {
        foreach ($fields as $field) {
            $this->addField($field, $replace);
        }

        return $this;
    }

    /** @return array<string, SettingField> */
    public function fields(): array
    {
        return $this->fields;
    }
}
