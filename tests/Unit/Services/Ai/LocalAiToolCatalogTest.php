<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use NixPHP\CMS\Contracts\LocalAiToolMetadataInterface;
use NixPHP\CMS\Services\Ai\LocalAiToolCatalog;
use PHPUnit\Framework\TestCase;

final class LocalAiToolCatalogTest extends TestCase
{
    public function testEveryRegisteredCoreToolDeclaresRiskMetadata(): void
    {
        $bootstrap = (string) file_get_contents(BASE_PATH . '/bootstrap.php');
        preg_match_all('/tool\\(\\)->register\\(new ([A-Za-z0-9_]+)\\(\\)\\);/', $bootstrap, $matches);
        self::assertCount(20, $matches[1]);

        foreach ($matches[1] as $shortName) {
            $class = 'NixPHP\\CMS\\Mcp\\Tools\\' . $shortName;
            $tool = new $class();
            self::assertInstanceOf(LocalAiToolMetadataInterface::class, $tool, $class);
            $metadata = $tool->localAiMetadata();
            self::assertContains($metadata['risk'] ?? null, ['read', 'write'], $class);
            self::assertIsBool($metadata['destructive'] ?? null, $class);
        }
    }

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

    /**
     * Write detection used to substring-match the whole description, so prose
     * like "published asset status" flagged read-only tools as writes. The hard
     * filter then hid them from every request without write intent.
     */
    public function testReadToolsWithWriteVocabularyInProseStayReadOnly(): void
    {
        $definitions = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_system_health',
                'description' => 'Returns current NixCMS system health data. The result includes published asset status and loaded plugins.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'external_public_holidays',
                'description' => 'Returns public holidays for a country and year so owners can plan campaigns and publishing schedules.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        foreach ($definitions as $definition) {
            self::assertSame('read', $definition['meta']['risk'], $definition['name']);
            self::assertSame('read', $definition['meta']['mode'], $definition['name']);
            self::assertFalse($definition['meta']['destructive'], $definition['name']);
        }
    }

    public function testWriteToolsAreStillDetectedByName(): void
    {
        $definitions = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_article_draft_create',
                'description' => 'Creates a new article draft with Editor.js-style content blocks.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_article_draft_append',
                'description' => 'Appends plain text as paragraphs to an existing article draft.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        foreach ($definitions as $definition) {
            self::assertSame('write', $definition['meta']['risk'], $definition['name']);
            self::assertSame('write', $definition['meta']['mode'], $definition['name']);
        }
    }

    public function testLeadingInflectedWriteVerbsAreClassifiedConservatively(): void
    {
        $definitions = (new LocalAiToolCatalog())->enrich(array_map(
            static fn(string $description): array => [
                'name' => 'plugin_action_' . md5($description),
                'description' => $description,
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'Creates a record.',
                'Updates a record.',
                'Removes a record.',
                'Archives a record.',
            ],
        ));

        foreach ($definitions as $definition) {
            self::assertSame('write', $definition['meta']['risk'], $definition['description']);
            self::assertSame('write', $definition['meta']['mode'], $definition['description']);
        }

        self::assertTrue($definitions[2]['meta']['destructive']);
        self::assertTrue($definitions[3]['meta']['destructive']);
    }
}
