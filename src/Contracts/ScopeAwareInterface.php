<?php

declare(strict_types=1);

namespace src\Contracts;

use src\Enums\Scope;

interface ScopeAwareInterface
{
    public function forScope(Scope $scope): object;
}
