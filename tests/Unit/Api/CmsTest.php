<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Cms\Cms;
use Cms\Api\ArticleApi;
use Cms\Api\ContentApi;
use Cms\Models\Article;
use Cms\Repositories\ArticleRepository;
use Cms\Repositories\ArticleVersionRepository;
use PHPUnit\Framework\TestCase;

final class CmsTest extends TestCase
{
    public function testCmsThrowsForUnsupportedContentType(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Only Cms\Models\Article is currently supported. Add a domain implementation before introducing new content types.');

        // We can test this without needing repo() to be available
        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleVersionRepository = $this->createMock(ArticleVersionRepository::class);

        $cms = new Cms();
        $cms->content(\stdClass::class);
    }
}
