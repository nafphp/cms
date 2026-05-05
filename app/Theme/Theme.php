<?php

declare(strict_types=1);

namespace NixCMS\Theme;

/**
 * Frontend theme definition: body class, optional assets, and builder-visible surfaces.
 */
final class Theme
{
    private string $label;
    /** @var array<int, array{href: string, attributes: array<string, string|bool>}> */
    private array $stylesheets = [];
    /** @var array<int, array{src: string, attributes: array<string, string|bool>}> */
    private array $scripts = [];
    /** @var array<string, string> */
    private array $surfaces = [];

    private function __construct(private readonly string $id)
    {
        if ($this->id === '') {
            throw new \InvalidArgumentException('Theme id cannot be empty.');
        }

        $this->label = ucwords(str_replace(['-', '_'], ' ', $this->id));
    }

    public static function create(string $id): self
    {
        return new self(self::slug($id));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $label = trim($label);
        $this->label = $label === '' ? $this->label : $label;
        return $this;
    }

    public function className(): string
    {
        return 'theme-' . $this->id;
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function addStylesheet(string $href, array $attributes = []): self
    {
        $href = trim($href);
        if ($href === '') {
            throw new \InvalidArgumentException('Theme stylesheet href cannot be empty.');
        }

        $this->stylesheets[] = ['href' => $href, 'attributes' => $this->cleanAttributes($attributes)];
        return $this;
    }

    /**
     * @return array<int, array{href: string, attributes: array<string, string|bool>}>
     */
    public function stylesheets(): array
    {
        return $this->stylesheets;
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function addScript(string $src, array $attributes = []): self
    {
        $src = trim($src);
        if ($src === '') {
            throw new \InvalidArgumentException('Theme script src cannot be empty.');
        }

        $this->scripts[] = ['src' => $src, 'attributes' => $this->cleanAttributes($attributes)];
        return $this;
    }

    /**
     * @return array<int, array{src: string, attributes: array<string, string|bool>}>
     */
    public function scripts(): array
    {
        return $this->scripts;
    }

    public function addSurface(string $id, ?string $label = null): self
    {
        $id = self::slug($id);
        if ($id === '') {
            throw new \InvalidArgumentException('Theme surface id cannot be empty.');
        }

        $label = trim((string) ($label ?? ''));
        $this->surfaces[$id] = $label === '' ? ucwords(str_replace(['-', '_'], ' ', $id)) : $label;
        return $this;
    }

    /**
     * @param array<string, string>|array<int, string> $surfaces
     */
    public function addSurfaces(array $surfaces): self
    {
        foreach ($surfaces as $id => $label) {
            is_int($id) ? $this->addSurface((string) $label) : $this->addSurface((string) $id, (string) $label);
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function surfaces(): array
    {
        return $this->surfaces;
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toOption(): array
    {
        return ['value' => $this->id, 'label' => $this->label];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function surfaceOptions(): array
    {
        return array_map(
            static fn(string $id, string $label): array => ['value' => $id, 'label' => $label],
            array_keys($this->surfaces),
            array_values($this->surfaces),
        );
    }

    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    /**
     * @param array<string, string|bool> $attributes
     * @return array<string, string|bool>
     */
    private function cleanAttributes(array $attributes): array
    {
        $clean = [];
        foreach ($attributes as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || $value === false) {
                continue;
            }

            $clean[$name] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }
}
