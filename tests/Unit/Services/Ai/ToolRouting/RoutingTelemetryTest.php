<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ToolRouting;

use src\Services\Ai\ToolRouting\RoutingTelemetry;
use src\Services\Ai\ToolRouting\ToolContext;
use PHPUnit\Framework\TestCase;

final class RoutingTelemetryTest extends TestCase
{
    public function testDefaultFallbackMetadataDoesNotContainTheUserQuery(): void
    {
        $query = 'My private unreleased campaign is called Blue Heron.';
        $meta = (new RoutingTelemetry())->fallback(
            'embedding_model_missing',
            $query,
            ToolContext::fromArray(['area' => 'articleEditor', 'mode' => 'read']),
        );

        self::assertArrayNotHasKey('query', $meta);
        self::assertArrayNotHasKey('debug_query', $meta);
        self::assertSame(hash('sha256', $query), $meta['query_hash']);
        self::assertSame(mb_strlen($query), $meta['query_length']);
        self::assertNotContains($query, $meta);
    }
}
