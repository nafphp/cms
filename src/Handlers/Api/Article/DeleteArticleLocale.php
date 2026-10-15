<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Article;

use Naf\CMS\Models\Article;
use Psr\Http\Message\ResponseInterface;

use function Naf\CMS\cms;
use function Naf\json;

final class DeleteArticleLocale
{
    public function __invoke(string $articleId, string $language): ResponseInterface
    {
        $lang = cms()->languages()->normalize($language);
        if ($lang === null || !cms()->languages()->isKnown($lang) || (int) $articleId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid article or language.'], 400);
        }

        return json([
            'success' => cms()->content(Article::class)->deleteLocale((int) $articleId, $lang),
        ]);
    }
}
