<?php

declare(strict_types=1);

namespace Naf\CMS\Settings;

/** @api */
final class SettingField
{
    /** @var array<int|string, string> */
    private array $options       = [];
    private ?string $description = null;
    private mixed $defaultValue  = null;

    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type = 'text',
    ) {
    }

    public static function text(string $key, string $label): self
    {
        return new self($key, $label);
    }

    public static function textarea(string $key, string $label): self
    {
        return new self($key, $label, 'textarea');
    }

    /** @param array<int|string, string> $options */
    public static function select(string $key, string $label, array $options): self
    {
        return (new self($key, $label, 'select'))->setOptions($options);
    }

    /** @param array<int|string, string> $options */
    public static function multiselect(string $key, string $label, array $options): self
    {
        return (new self($key, $label, 'multiselect'))->setOptions($options);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): string
    {
        return $this->type;
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

    public function defaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(mixed $defaultValue): self
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /** @return array<int|string, string> */
    public function options(): array
    {
        return $this->options;
    }

    /** @param array<int|string, string> $options */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }
}
