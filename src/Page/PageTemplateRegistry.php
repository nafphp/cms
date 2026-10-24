<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Code-defined page templates offered by the page builder.
 *
 * Three layers feed the registry, each overriding the one before it by id:
 *
 * 1. the templates shipped with this package,
 * 2. templates plugins register through `cms()->pageTemplates()->add(...)`,
 * 3. the host's `src/Resources/pages/page_templates.php`.
 *
 * The host file is read lazily on the first read, so it wins regardless of
 * plugin order. User-built layouts from the `page_layouts` table are a
 * separate feature and are not part of this registry.
 * @api
 */
final class PageTemplateRegistry
{
    /** @var array<string, Layout> */
    private array $templates = [];
    /** @var array<string, Layout> */
    private array $hostTemplates = [];
    /** @var array<string, true> */
    private array $removed       = [];
    private bool $defaultsBooted = false;
    private bool $hostBooted     = false;

    /**
     * @param string|null $defaultsFile Package template file, loaded before any registration
     * @param string|null $hostFile     Host template file, loaded on first read and always wins
     */
    public function __construct(
        private readonly ?string $defaultsFile = null,
        private readonly ?string $hostFile = null,
    ) {
    }

    /**
     * Register a template. Pass $replace to override a template with the same id.
     */
    public function add(Layout $layout, bool $replace = false): self
    {
        $this->bootDefaults();

        $id = $this->assertValid($layout);
        if (isset($this->templates[$id]) && !$replace) {
            throw new LogicException(sprintf(
                'Page template "%s" is already registered. Pass replace: true to override it.',
                $id,
            ));
        }

        $this->templates[$id] = $layout;
        unset($this->removed[$id]);

        return $this;
    }

    /**
     * @param iterable<Layout|array<string, mixed>> $layouts
     */
    public function addMany(iterable $layouts, bool $replace = false): self
    {
        foreach ($layouts as $layout) {
            $this->add($layout instanceof Layout ? $layout : Layout::fromDefinition($layout), $replace);
        }

        return $this;
    }

    /**
     * Register every template returned by a PHP file, e.g. a plugin's own
     * `page_templates.php`. The file must return a list of Layout objects or
     * template definition arrays.
     */
    public function load(string $file, bool $replace = false): self
    {
        return $this->addMany($this->readFile($file), $replace);
    }

    /**
     * Hide a template from the builder, e.g. a shipped one a host does not want.
     *
     * Pages already stored with this template keep their `template_id`; they
     * fall back to the default template when rendered.
     */
    public function remove(string $id): self
    {
        $this->bootDefaults();

        $id = trim($id);
        unset($this->templates[$id]);
        $this->removed[$id] = true;

        return $this;
    }

    public function has(string $id): bool
    {
        return $this->get($id) instanceof Layout;
    }

    public function get(string $id): ?Layout
    {
        return $this->all()[trim($id)] ?? null;
    }

    public function require(string $id): Layout
    {
        return $this->get($id) ?? throw new RuntimeException(sprintf('Page template "%s" is not registered.', $id));
    }

    /**
     * @return array<string, Layout>
     */
    public function all(): array
    {
        $this->bootDefaults();
        $this->bootHost();

        $templates = [...$this->templates, ...$this->hostTemplates];
        foreach (array_keys($this->removed) as $id) {
            unset($templates[$id]);
        }

        return $templates;
    }

    /**
     * Builder/storage definitions of every registered template.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return Layout::definitions(array_values($this->all()));
    }

    private function bootDefaults(): void
    {
        if ($this->defaultsBooted) {
            return;
        }

        $this->defaultsBooted = true;

        if ($this->defaultsFile !== null) {
            foreach ($this->readFile($this->defaultsFile, optional: true) as $layout) {
                $layout = $layout instanceof Layout ? $layout : Layout::fromDefinition($layout);

                $this->templates[$this->assertValid($layout)] = $layout;
            }
        }
    }

    private function bootHost(): void
    {
        if ($this->hostBooted) {
            return;
        }

        $this->hostBooted = true;

        if ($this->hostFile === null) {
            return;
        }

        $defaults = $this->defaultsFile !== null ? realpath($this->defaultsFile) : false;
        $host     = realpath($this->hostFile);
        if ($host === false || $host === $defaults) {
            return;
        }

        foreach ($this->readFile($this->hostFile, optional: true) as $layout) {
            $layout = $layout instanceof Layout ? $layout : Layout::fromDefinition($layout);
            $id     = $this->assertValid($layout);

            $this->hostTemplates[$id] = $layout;
            unset($this->removed[$id]);
        }
    }

    /**
     * @return array<int|string, Layout|array<string, mixed>>
     */
    private function readFile(string $file, bool $optional = false): array
    {
        if (!is_file($file)) {
            if ($optional) {
                return [];
            }

            throw new InvalidArgumentException(sprintf('Page template file "%s" does not exist.', $file));
        }

        $layouts = (static fn(string $path): mixed => require $path)($file);
        if (!is_array($layouts)) {
            throw new LogicException(sprintf('Page template file "%s" must return an array of layouts.', $file));
        }

        return array_filter(
            $layouts,
            static fn(mixed $layout): bool => $layout instanceof Layout || is_array($layout),
        );
    }

    private function assertValid(Layout $layout): string
    {
        $id = trim((string) $layout->id());
        if ($id === '') {
            throw new InvalidArgumentException('Page template needs an id. Use Layout::define($id, $label, $template).');
        }

        if (($layout->templateView() ?? '') === '') {
            throw new InvalidArgumentException(sprintf('Page template "%s" needs a view.', $id));
        }

        return $id;
    }
}
