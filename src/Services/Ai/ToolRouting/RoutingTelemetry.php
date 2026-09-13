<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Ai\ToolRouting;

use Naf\CMS\Services\Ai\ToolRouting\ToolContext;
use function Naf\CMS\cms;

final class RoutingTelemetry
{
    /** @return array<string, mixed> */
    public function fallback(string $reason, string $input, ToolContext $context): array
    {
        $normalized = trim($input);
        $meta = [
            'reason' => $reason,
            'query_hash' => hash('sha256', $normalized),
            'query_length' => mb_strlen($normalized),
            'area' => $context->area,
            'mode' => $context->requestedMode(),
            'capabilities' => $context->requestedCapabilities(),
        ];

        if ((bool) cms()->settings()->value('ai.routing_debug', false)) {
            $meta['debug_query'] = mb_substr($normalized, 0, 500);
            $meta['debug_truncated'] = mb_strlen($normalized) > 500;
        }

        return $meta;
    }
}
