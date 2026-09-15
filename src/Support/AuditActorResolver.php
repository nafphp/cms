<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use function Naf\CMS\user;

class AuditActorResolver
{
    public function resolve(): ?AuditActorInfo
    {
        $helper = user();
        $actor  = $helper?->get();
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
