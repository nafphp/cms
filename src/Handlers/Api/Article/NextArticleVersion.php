<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Article;

use Naf\CMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;
use function Naf\CMS\cms;

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
