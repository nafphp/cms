<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api;

use Naf\CMS\Exceptions\AuthException;
use Naf\CMS\Handlers\Api\Article\DeleteArticleLocale;
use Naf\CMS\Handlers\Api\Article\DeleteArticleVersion;
use Naf\CMS\Handlers\Api\Article\EnqueueArticleTranslation;
use Naf\CMS\Handlers\Api\Article\NextArticleVersion;
use Naf\CMS\Handlers\Api\Article\PublishArticle;
use Naf\CMS\Handlers\Api\Article\ReviewArticle;
use Naf\CMS\Handlers\Api\Article\UploadArticleImage;
use Naf\CMS\Handlers\Api\Form\DeleteForm;
use Naf\CMS\Handlers\Api\Form\SubmitForm;
use Naf\CMS\Handlers\Api\Media\DeleteMedia;
use Naf\CMS\Handlers\Api\Media\ListMedia;
use Naf\CMS\Handlers\Api\Media\UpdateMedia;
use Naf\CMS\Handlers\Api\Media\UploadMedia;
use Naf\CMS\Handlers\Api\Navigation\DeleteNavigation;
use Naf\CMS\Handlers\Api\Navigation\PublishNavigation;
use Naf\CMS\Handlers\Api\Page\DeletePage;
use Naf\CMS\Handlers\Api\Page\DeletePageLayout;
use Naf\CMS\Handlers\Api\Page\StorePageLayout;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

final class ContentWriteHandlersTest extends DatabaseTestCase
{
    public function testArticleWriteHandlersValidateTheirPublicBoundary(): void
    {
        JsonRequest::bind([]);

        $cases = [
            [(new DeleteArticleLocale())('0', 'unknown'), 400],
            [(new DeleteArticleVersion())('0', 'unknown', '0'), 400],
            [(new PublishArticle())('0', 'unknown'), 400],
            [(new UploadArticleImage())(), 400],
        ];

        foreach ($cases as [$response, $status]) {
            self::assertSame($status, $response->getStatusCode());
        }

        $queued = (new EnqueueArticleTranslation())('0');
        self::assertSame(200, $queued->getStatusCode());
        self::assertFalse($this->json($queued)['success']);

        $next = (new NextArticleVersion())('0', 'de');
        self::assertSame(200, $next->getStatusCode());
        self::assertArrayHasKey('version', $this->json($next));
    }

    public function testReviewArticleRejectsAnUnauthenticatedCallerBeforeStorage(): void
    {
        JsonRequest::bind(['action' => 'approve']);
        \Naf\CMS\user(reset: true);

        $this->expectException(AuthException::class);
        (new ReviewArticle())('1', 'de');
    }

    public function testPageWriteHandlersRejectInvalidPayloads(): void
    {
        JsonRequest::bind([]);

        foreach ([
            (new DeletePage())('0'),
            (new DeletePageLayout())('0'),
            (new StorePageLayout())(),
        ] as $response) {
            self::assertSame(400, $response->getStatusCode());
            self::assertFalse($this->json($response)['success']);
        }
    }

    public function testMediaWriteHandlersRejectInvalidPayloads(): void
    {
        $this->createSchema(<<<SQL
            CREATE TABLE media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id TEXT NOT NULL,
                disk TEXT NOT NULL,
                visibility TEXT NOT NULL,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                storage_path TEXT NOT NULL,
                file_path TEXT NOT NULL,
                public_url TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                extension TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                width INTEGER NULL,
                height INTEGER NULL,
                alt_text TEXT NULL,
                uploaded_by INTEGER NULL,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL
            )
        SQL);
        JsonRequest::bind([]);

        foreach ([
            (new DeleteMedia())('0'),
            (new UpdateMedia())('0'),
            (new UploadMedia())(),
        ] as $response) {
            self::assertSame(400, $response->getStatusCode());
            self::assertFalse($this->json($response)['success']);
        }

        $list = (new ListMedia())();
        self::assertSame(200, $list->getStatusCode());
        self::assertSame(['rows', 'summary'], array_keys($this->json($list)));
    }

    public function testFormWriteHandlersRejectInvalidPayloads(): void
    {
        JsonRequest::bind([]);
        $delete = (new DeleteForm())('0');
        self::assertSame(400, $delete->getStatusCode());

        $submit = (new SubmitForm())('missing-form');
        self::assertSame(404, $submit->getStatusCode());
        self::assertFalse($this->json($submit)['success']);
    }

    public function testNavigationWriteHandlersRejectInvalidIdentifiers(): void
    {
        foreach ([
            (new DeleteNavigation())('0'),
            (new PublishNavigation())('0'),
        ] as $response) {
            self::assertSame(400, $response->getStatusCode());
            self::assertFalse($this->json($response)['success']);
        }
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
