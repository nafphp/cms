<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Article;

use NixCMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixCMS\cms;

final class NextArticleVersion
{
    public function __invoke(string $articleId, string $language): ResponseInterface
    {
        return json([
            'success' => true,
            'version' => cms()->contentTypes()->requireByModel(Article::class)->form()->nextVersion((int) $articleId, $language),
        ]);
    }
}
