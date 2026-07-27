<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use NixPHP\CMS\Services\Ai\LocalAiToolCatalog;
use PHPUnit\Framework\TestCase;

final class LocalAiToolCatalogTest extends TestCase
{
    public function testEnrichAddsRoutingMetadataForTools(): void
    {
        $definitions = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_article_draft_create',
                'description' => 'Creates an article draft.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                    ],
                ],
            ],
        ]);

        $meta = $definitions[0]['meta'];

        self::assertSame('cms_write', $meta['category']);
        self::assertSame('write', $meta['risk']);
        self::assertSame('write', $meta['mode']);
        self::assertContains('content.write', $meta['capabilities']);
        self::assertStringContainsString('title', $meta['routingText']);
    }

    public function testPluginMetadataRemainsAuthoritativeWhileMissingValuesAreEnriched(): void
    {
        $definition = (new LocalAiToolCatalog())->enrich([[
            'name' => 'plugin_report_export',
            'description' => 'Exports a report.',
            'inputSchema' => ['type' => 'object', 'properties' => []],
            'meta' => [
                'title' => 'Quarterly report export',
                'routingText' => 'Use for approved quarterly finance exports.',
                'capabilities' => ['reports.export'],
                'mode' => 'write',
                'risk' => 'write',
                'examples' => ['Export the Q2 report.'],
            ],
        ]])[0];

        self::assertSame('Quarterly report export', $definition['meta']['title']);
        self::assertSame('Use for approved quarterly finance exports.', $definition['meta']['routingText']);
        self::assertSame(['reports.export'], $definition['meta']['capabilities']);
        self::assertSame(['Export the Q2 report.'], $definition['meta']['examples']);
        self::assertArrayHasKey('category', $definition['meta']);
    }
}
