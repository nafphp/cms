<?php

declare(strict_types=1);

namespace NixCMS\Theme;

final class ThemeRegistry
{
    /** @var array<string, Theme> */
    private array $themes = [];

    public function __construct()
    {
        $this->add(
            Theme::create('default')
                ->setLabel('Default')
                ->addSurface('none', 'None')
                ->addSurface('soft', 'Soft')
                ->addSurface('card', 'Card')
                ->addSurface('accent', 'Accent'),
        );
    }

    public function add(Theme $theme, bool $replace = false): self
    {
        if (isset($this->themes[$theme->id()]) && !$replace) {
            throw new \LogicException(sprintf('Theme "%s" is already registered.', $theme->id()));
        }

        $this->themes[$theme->id()] = $theme;
        return $this;
    }

    /**
     * @param iterable<Theme> $themes
     */
    public function addMany(iterable $themes, bool $replace = false): self
    {
        foreach ($themes as $theme) {
            $this->add($theme, $replace);
        }

        return $this;
    }

    public function get(?string $id): ?Theme
    {
        $id = Theme::slug((string) $id);
        return $id === '' ? null : ($this->themes[$id] ?? null);
    }

    public function default(): Theme
    {
        return $this->themes['default'];
    }

    public function resolve(?string $id): Theme
    {
        return $this->get($id) ?? $this->default();
    }

    /**
     * @return array<string, Theme>
     */
    public function all(): array
    {
        return $this->themes;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_values(array_map(
            static fn(Theme $theme): array => $theme->toOption(),
            $this->themes,
        ));
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public function surfaceOptionsByTheme(): array
    {
        $options = [];
        foreach ($this->themes as $theme) {
            $options[$theme->id()] = $theme->surfaceOptions();
        }

        return $options;
    }
}
