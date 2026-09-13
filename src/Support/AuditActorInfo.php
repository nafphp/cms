<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

final class AuditActorInfo
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $label,
        public readonly bool $isLoggedIn,
    ) {
    }

    public static function system(): self
    {
        return new self(null, 'System', false);
    }
}
