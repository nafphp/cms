<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use NixCMS\Services\Ai\LocalAiToolCatalog;
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
}
