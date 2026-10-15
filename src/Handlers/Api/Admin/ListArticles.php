<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Admin;

use Naf\CMS\Content\Article\ArticleContentType;
use Naf\CMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function Naf\CMS\cms;
use function Naf\json;

final class ListArticles
{
    public function __invoke(): ResponseInterface
    {
        $type = cms()->contentTypes()->requireByModel(Article::class);
        if (!$type instanceof ArticleContentType) {
            throw new RuntimeException('Registered article content type must be an ArticleContentType.');
        }

        return json($type->catalog()->overview()['articles']);
    }
}
