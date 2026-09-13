<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

use Naf\CMS\Enums\Scope;

interface ScopeAwareInterface
{
    public function forScope(Scope $scope): object;
}
