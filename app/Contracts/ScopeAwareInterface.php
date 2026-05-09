<?php

declare(strict_types=1);

namespace NixCMS\Contracts;

use NixCMS\Enums\Scope;

interface ScopeAwareInterface
{
    public function forScope(Scope $scope): object;
}
