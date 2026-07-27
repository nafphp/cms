<?php

declare(strict_types=1);

namespace src\Page;

/**
 * Render description for a concrete .phtml template and its optional parent.
 */
final class Template
{
    private ?string $view = null;
    private ?string $parent = null;
    private ?string $themeId = null;
    /** @var array<string, string> */
    private array $bodyClasses = [];
    /** @var array<int, array{href: string, attributes: array<string, string|bool>}> */
    private array $stylesheets = [];
    /** @var array<int, array{src: string, attributes: array<string, string|bool>}> */
    private array $scripts = [];

    public function __construct(?string $view = null)
    {
        $this->setView($view);
    }

    public static function create(?string $view = null): self
    {
        return new self($view);
    }

    public function view(): ?string
    {
        return $this->view;
    }

    public function setView(?string $view): self
    {
        if ($view === null || trim($view) === '') {
            $this->view = null;
            return $this;
        }

        $view = trim($view);
        $this->view = str_contains($view, '.') || str_contains($view, '/')
            ? $view
            : 'page.templates.' . $view;

        return $this;
    }

    public function parent(): ?string
    {
        return $this->parent;
    }

    public function themeId(): ?string
    {
        return $this->themeId;
    }

    public function setTheme(?string $themeId): self
    {
        $themeId = $themeId === null ? null : trim($themeId);
        $this->themeId = $themeId === '' ? null : $themeId;
        return $this;
    }

    public function setParent(?string $parent): self
    {
        if ($parent === null || trim($parent) === '') {
            $this->parent = null;
            return $this;
        }

        $parent = trim($parent);
        $this->parent = str_contains($parent, '.') || str_contains($parent, '/')
            ? $parent
            : 'layouts.' . $parent;

        return $this;
    }

    public function addBodyClass(string $class): self
    {
        foreach (preg_split('/\s+/', trim($class)) ?: [] as $name) {
            if ($name !== '') {
                $this->bodyClasses[$name] = $name;
            }
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function bodyClasses(): array
    {
        return array_values($this->bodyClasses);
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function addStylesheet(string $href, array $attributes = []): self
    {
        $href = trim($href);
        if ($href === '') {
            throw new \InvalidArgumentException('Stylesheet href cannot be empty.');
        }

        $this->stylesheets[] = [
            'href' => $href,
            'attributes' => $this->cleanAttributes($attributes),
        ];

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
            throw new \InvalidArgumentException('Script src cannot be empty.');
        }

        $this->scripts[] = [
            'src' => $src,
            'attributes' => $this->cleanAttributes($attributes),
        ];

        return $this;
    }

    /**
     * @return array<int, array{src: string, attributes: array<string, string|bool>}>
     */
    public function scripts(): array
    {
        return $this->scripts;
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
