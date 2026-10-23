<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

use Naf\CMS\Enums\Scope;

/** @api */
interface ScopeAwareInterface
{
    public function forScope(Scope $scope): object;
}
