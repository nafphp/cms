<?php

declare(strict_types=1);

namespace NixPHP\CMS\Contracts;

use NixPHP\CMS\Enums\Scope;

interface ScopeAwareInterface
{
    public function forScope(Scope $scope): object;
}
