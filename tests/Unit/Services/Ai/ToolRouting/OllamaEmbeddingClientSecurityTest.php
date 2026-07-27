<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ToolRouting;

use NixPHP\CMS\Services\Ai\LocalAiToolCatalog;
use NixPHP\CMS\Services\Ai\ToolRouting\CosineToolRouter;
use NixPHP\CMS\Services\Ai\ToolRouting\KeywordToolRouter;
use NixPHP\CMS\Services\Ai\ToolRouting\OllamaEmbeddingClient;
use NixPHP\CMS\Services\Ai\ToolRouting\ToolContext;
use NixPHP\CMS\Services\Http\OutboundHttpClient;
use NixPHP\CMS\Services\Http\OutboundHttpPolicy;
use NixPHP\CMS\Services\Http\OutboundHttpProfile;
use Tests\Support\NixPHPTestCase;

final class OllamaEmbeddingClientSecurityTest extends NixPHPTestCase
{
    public function testLocalOllamaLoopbackRemainsAvailable(): void
    {
        $requests = [];
        $http = new OutboundHttpClient(
            new OutboundHttpPolicy(),
            static function ($target, string $method, string $body) use (&$requests): array {
                $requests[] = ['url' => $target->url, 'method' => $method, 'body' => json_decode($body, true)];
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => '{"embeddings":[[0.25,0.75]]}',
                ];
            },
        );
        $client = new OllamaEmbeddingClient(
            'http://127.0.0.1:11434',
            'security-test-model',
            cacheDirectory: sys_get_temp_dir() . '/nixcms-embedding-security-' . bin2hex(random_bytes(6)),
            httpClient: $http,
            httpProfile: OutboundHttpProfile::ollama([11434]),
        );

        self::assertSame([0.25, 0.75], $client->embed('loopback probe ' . bin2hex(random_bytes(4))));
        self::assertSame('http://127.0.0.1:11434/api/embed', $requests[0]['url']);
        self::assertSame('POST', $requests[0]['method']);
    }

    public function testBlockedEmbeddingTargetFallsBackToKeywordRouterWithoutTransportCall(): void
    {
        $transportCalled = false;
        $http = new OutboundHttpClient(
            new OutboundHttpPolicy(),
            static function () use (&$transportCalled): array {
                $transportCalled = true;
                return ['status' => 200, 'headers' => [], 'body' => '{"embeddings":[[1,0]]}'];
            },
        );
        $embeddings = new OllamaEmbeddingClient(
            'http://169.254.169.254:11434',
            'blocked-model',
            cacheDirectory: sys_get_temp_dir() . '/nixcms-embedding-blocked-' . bin2hex(random_bytes(6)),
            httpClient: $http,
            httpProfile: OutboundHttpProfile::ollama([11434]),
        );
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_queue_status',
                'description' => 'Returns queue status and pending jobs.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_article_draft_create',
                'description' => 'Creates an article draft.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);
        $router = new CosineToolRouter($tools, $embeddings, fallback: new KeywordToolRouter($tools));

        $selected = $router->select('Zeige den Queue Status', ToolContext::fromArray(['area' => 'dashboard']), 2);

        self::assertFalse($transportCalled);
        self::assertSame('cms_queue_status', $selected[0]['name']);
        self::assertNotContains('cosine', $selected[0]['route']['reasons']);
    }
}
