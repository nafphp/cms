<?php

declare(strict_types=1);

namespace NixCMS\Page;

final class Template
{
    private ?string $view = null;
    private ?string $layout = null;
    /** @var array<string, PageBlock> */
    private array $blocks = [];
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

    public function layout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(?string $layout): self
    {
        if ($layout === null || trim($layout) === '') {
            $this->layout = null;
            return $this;
        }

        $layout = trim($layout);
        $this->layout = str_contains($layout, '.') || str_contains($layout, '/')
            ? $layout
            : 'layouts.' . $layout;

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addBlock(string $id, string $view, array $data = []): self
    {
        $block = new PageBlock($id, $view, $data);

        if (isset($this->blocks[$block->id()])) {
            throw new \LogicException(sprintf('Template already has a block "%s".', $block->id()));
        }

        $this->blocks[$block->id()] = $block;
        return $this;
    }

    /**
     * @return array<string, PageBlock>
     */
    public function blocks(): array
    {
        return $this->blocks;
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
