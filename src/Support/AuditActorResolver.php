<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support;

use NixPHP\CMS\Support\AuditActorInfo;
use function NixPHP\CMS\user;

class AuditActorResolver
{
    public function resolve(): ?AuditActorInfo
    {
        $helper = user();
        $actor = $helper?->get();
        if ($actor === null) {
            return new AuditActorInfo(
                null,
                $helper?->isLoggedIn() === true ? 'System admin' : 'System',
                $helper?->isLoggedIn() === true,
            );
        }

        return new AuditActorInfo(
            $actor->getId(),
            $actor->getDisplayName(),
            true,
        );
    }
}
