<?php

declare(strict_types=1);

namespace Naf\CMS\Enums;

enum ToolRisk: string
{
    case READ = 'read';
    case WRITE = 'write';

    public function requiresConfirmation(): bool
    {
        return $this === self::WRITE;
    }
}
