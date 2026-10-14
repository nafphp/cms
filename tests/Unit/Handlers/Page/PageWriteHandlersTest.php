<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Page;

use Naf\CMS\Handlers\Api\Theme\StoreTheme as StoreThemeApi;
use Naf\CMS\Handlers\Page\Article\StoreArticleDraft;
use Naf\CMS\Handlers\Page\Form\StoreForm;
use Naf\CMS\Handlers\Page\Navigation\StoreNavigation;
use Naf\CMS\Handlers\Page\Theme\StoreTheme as StoreThemePage;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

final class PageWriteHandlersTest extends DatabaseTestCase
{
    public function testContentPageHandlersRejectIncompletePayloadsBeforePersistence(): void
    {
        JsonRequest::bind([]);

        foreach ([
            new StoreArticleDraft(),
            new StoreForm(),
            new StoreNavigation(),
        ] as $handler) {
            $response = $handler();
            $payload  = $this->json($response);
            self::assertSame(400, $response->getStatusCode(), $handler::class);
            self::assertFalse($payload['success'], $handler::class);
            self::assertNotSame([], $payload['errors'], $handler::class);
        }
    }

    public function testThemeHandlersRejectAnEmptyThemeWithoutWritingAssets(): void
    {
        JsonRequest::bind([]);

        $apiResponse = (new StoreThemeApi())();
        self::assertSame(400, $apiResponse->getStatusCode());
        self::assertFalse($this->json($apiResponse)['success']);

        $pageResponse = (new StoreThemePage())();
        self::assertSame(302, $pageResponse->getStatusCode());
        self::assertStringContainsString('error=', $pageResponse->getHeaderLine('Location'));
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
