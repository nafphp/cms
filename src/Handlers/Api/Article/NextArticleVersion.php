<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Article;

use Naf\CMS\Content\Article\ArticleContentType;
use Naf\CMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function Naf\CMS\cms;
use function Naf\json;

final class NextArticleVersion
{
    public function __invoke(string $articleId, string $language): ResponseInterface
    {
        $type = cms()->contentTypes()->requireByModel(Article::class);
        if (!$type instanceof ArticleContentType) {
            throw new RuntimeException('Registered article content type must be an ArticleContentType.');
        }

        return json([
            'success' => true,
            'version' => $type->form()->nextVersion((int) $articleId, $language),
        ]);
    }
}
