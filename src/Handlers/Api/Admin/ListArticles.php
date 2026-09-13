<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Admin;

use Naf\CMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;
use function Naf\CMS\cms;

final class ListArticles
{
    public function __invoke(): ResponseInterface
    {
        return json(cms()->contentTypes()->requireByModel(Article::class)->catalog()->overview()['articles']);
    }
}
