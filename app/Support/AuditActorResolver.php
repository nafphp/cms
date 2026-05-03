<?php

declare(strict_types=1);

namespace NixCMS\Support;

class AuditActorResolver
{
    public function resolve(): ?AuditActorInfo
    {
        if (!function_exists('user')) {
            return AuditActorInfo::system();
        }

        $helper = \user();
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
