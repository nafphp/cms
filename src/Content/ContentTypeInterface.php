<?php

declare(strict_types=1);

namespace src\Content;

use NixPHP\ORM\Core\EntityInterface;

interface ContentTypeInterface
{
    public function id(): string;

    public function label(): string;

    /**
     * @return class-string<EntityInterface>
     */
    public function modelClass(): string;

    public function api(): object;
}
