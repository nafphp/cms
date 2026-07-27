<?php

declare(strict_types=1);

namespace src\Page;

use use src\Enums\Permission;

final readonly class PageDataHandler
{
    /**
     * @param callable|array{0: class-string, 1: string}|class-string $handler
     */
    public function __construct(
        public mixed $handler,
        public string $method = 'POST',
        public ?Permission $permission = null,
    ) {
    }

    public function method(): string
    {
        return strtoupper(trim($this->method));
    }
}
