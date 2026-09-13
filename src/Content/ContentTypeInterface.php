<?php

declare(strict_types=1);

namespace Naf\CMS\Content;

use Naf\ORM\Core\EntityInterface;

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
