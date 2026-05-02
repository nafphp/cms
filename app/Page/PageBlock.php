<?php

declare(strict_types=1);

namespace NixCMS\Page;

final readonly class PageBlock
{
    private string $id;
    private string $view;
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $id,
        string $view,
        array $data = [],
    ) {
        $id = trim($id);
        $view = trim($view);

        if ($id === '') {
            throw new \InvalidArgumentException('Page block id cannot be empty.');
        }

        if ($view === '') {
            throw new \InvalidArgumentException(sprintf('Page block "%s" needs a view.', $id));
        }

        $this->id = $id;
        $this->view = $view;
        $this->data = $data;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function view(): string
    {
        return $this->view;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
