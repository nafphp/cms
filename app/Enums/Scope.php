<?php

declare(strict_types=1);

namespace NixCMS\Enums;

enum Scope: string
{
    case FRONTEND = 'frontend';
    case ADMIN = 'admin';

    public static function normalize(self|string $scope): self
    {
        if ($scope instanceof self) {
            return $scope;
        }

        $scope = strtolower(trim($scope));
        return self::tryFrom($scope) ?? throw new \InvalidArgumentException(sprintf(
            'Unsupported CMS scope "%s".',
            $scope
        ));
    }
}
