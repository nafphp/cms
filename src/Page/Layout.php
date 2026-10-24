<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use LogicException;

/**
 * Page-level composition: one layout template plus the blocks rendered into it.
 * @api
 */
final class Layout
{
    private Template $template;
    private ?string $id          = null;
    private ?string $label       = null;
    private ?string $description = null;
    private string $kind         = 'static';
    /** @var array<string, mixed> */
    private array $metadata = [];
    /** @var array<string, PageBlock> */
    private array $blocks = [];

    public function __construct(string|Template $template)
    {
        $this->template = is_string($template) ? new Template($template) : $template;
    }

    public static function create(string|Template $template): self
    {
        return new self($template);
    }

    public static function define(string $id, string $label, string|Template $template): self
    {
        return self::create($template)->setId($id)->setLabel($label);
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $id       = $id === null ? null : trim($id);
        $this->id = $id === '' ? null : $id;

        return $this;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $label       = $label === null ? null : trim($label);
        $this->label = $label === '' ? null : $label;

        return $this;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $description       = $description === null ? null : trim($description);
        $this->description = $description === '' ? null : $description;

        return $this;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): self
    {
        $kind       = trim($kind);
        $this->kind = $kind === '' ? 'static' : $kind;

        return $this;
    }

    public function setMetadata(string $key, mixed $value): self
    {
        $key = trim($key);
        if ($key !== '') {
            $this->metadata[$key] = $value;
        }

        return $this;
    }

    public function template(): Template
    {
        return $this->template;
    }

    public function templateView(): ?string
    {
        return $this->template->view();
    }

    public function parent(): ?string
    {
        return $this->template->parent();
    }

    public function setParent(?string $parent): self
    {
        $this->template->setParent($parent);

        return $this;
    }

    public function themeId(): ?string
    {
        return $this->template->themeId();
    }

    public function setTheme(?string $themeId): self
    {
        $this->template->setTheme($themeId);

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addBlock(string $id, string|Template $template, array $data = []): self
    {
        $block = new PageBlock($id, $template, $data);

        if (isset($this->blocks[$block->id()])) {
            throw new LogicException(sprintf('Layout already has a block "%s".', $block->id()));
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
        $this->template->addBodyClass($class);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function bodyClasses(): array
    {
        return $this->template->bodyClasses();
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function addStylesheet(string $href, array $attributes = []): self
    {
        $this->template->addStylesheet($href, $attributes);

        return $this;
    }

    /**
     * @return array<int, array{href: string, attributes: array<string, string|bool>}>
     */
    public function stylesheets(): array
    {
        return $this->template->stylesheets();
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function addScript(string $src, array $attributes = []): self
    {
        $this->template->addScript($src, $attributes);

        return $this;
    }

    /**
     * @return array<int, array{src: string, attributes: array<string, string|bool>}>
     */
    public function scripts(): array
    {
        return $this->template->scripts();
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blocksAreDeletable = $this->kind === 'custom';

        return array_filter([
            'id'          => $this->id,
            'label'       => $this->label,
            'kind'        => $this->kind,
            'layout'      => $this->parent(),
            'view'        => $this->templateView(),
            'description' => $this->description,
            'blocks'      => array_map(
                static fn(PageBlock $block): array => [
                    'id'        => $block->id(),
                    'deletable' => $blocksAreDeletable,
                    ...$block->data(),
                ],
                array_values($this->blocks),
            ),
            ...$this->metadata,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * Rebuild a layout from a definition array as produced by definition().
     *
     * Slot blocks render through `page.blocks.empty`, the same placeholder the
     * shipped templates use; the builder replaces them with stored content.
     *
     * @param array<string, mixed> $definition
     */
    public static function fromDefinition(array $definition): self
    {
        $view     = isset($definition['view']) ? (string) $definition['view'] : null;
        $template = Template::create($view)->setParent(
            isset($definition['layout']) ? (string) $definition['layout'] : null,
        );

        $layout = self::create($template)
            ->setId(isset($definition['id']) ? (string) $definition['id'] : null)
            ->setLabel(isset($definition['label']) ? (string) $definition['label'] : null)
            ->setDescription(isset($definition['description']) ? (string) $definition['description'] : null)
            ->setKind((string) ($definition['kind'] ?? 'static'));

        foreach (is_array($definition['blocks'] ?? null) ? $definition['blocks'] : [] as $block) {
            if (!is_array($block) || trim((string) ($block['id'] ?? '')) === '') {
                continue;
            }

            $data = $block;
            unset($data['id'], $data['deletable']);
            $layout->addBlock((string) $block['id'], 'page.blocks.empty', $data);
        }

        $reserved = ['id', 'label', 'kind', 'layout', 'view', 'description', 'blocks'];
        foreach ($definition as $key => $value) {
            if (is_string($key) && !in_array($key, $reserved, true)) {
                $layout->setMetadata($key, $value);
            }
        }

        return $layout;
    }

    /**
     * @param iterable<array<string, mixed>|self> $layouts
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(iterable $layouts): array
    {
        $definitions = [];

        foreach ($layouts as $layout) {
            if ($layout instanceof self) {
                $definitions[] = $layout->definition();
                continue;
            }

            if (is_array($layout)) {
                $definitions[] = $layout;
            }
        }

        return $definitions;
    }
}
