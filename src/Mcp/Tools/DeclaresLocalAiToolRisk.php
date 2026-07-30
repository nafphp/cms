<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\CMS\Enums\ToolRisk;

/**
 * Shared implementation for tools that only need to declare risk metadata.
 *
 * Richer tools may implement localAiMetadata() directly.
 */
trait DeclaresLocalAiToolRisk
{
    /** @return array{risk: string, destructive: bool, autoRun: bool} */
    public function localAiMetadata(): array
    {
        return [
            'risk' => self::AI_RISK->value,
            'destructive' => self::AI_DESTRUCTIVE,
            'autoRun' => false,
        ];
    }
}
