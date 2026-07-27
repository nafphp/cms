<?php

declare(strict_types=1);

namespace src\Page;

use src\Page\Template;

/**
 * Block context inside a page layout. Rendering is delegated to its template.
 */
final class PageBlock
{
    private string $id;
    private Template $template;
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $id,
        string|Template $template,
        array $data = [],
    ) {
        $id = trim($id);

        if ($id === '') {
            throw new \InvalidArgumentException('Page block id cannot be empty.');
        }

        $this->id = $id;
        $this->setTemplate($template);
        $this->data = $data;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function template(): Template
    {
        return $this->template;
    }

    public function setTemplate(string|Template $template): self
    {
        $template = is_string($template) ? new Template($template) : $template;
        if ($template->view() === null || $template->view() === '') {
            throw new \InvalidArgumentException(sprintf('Page block "%s" needs a view.', $this->id));
        }

        $this->template = $template;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
