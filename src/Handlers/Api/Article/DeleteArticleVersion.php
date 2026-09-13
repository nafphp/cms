<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Article;

use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;
use function Naf\CMS\cms;

final class DeleteArticleVersion
{
    public function __invoke(string $articleId, string $language, string $version): ResponseInterface
    {
        $lang = cms()->languages()->normalize($language);
        if ($lang === null || !cms()->languages()->isKnown($lang) || (int) $articleId <= 0 || (int) $version <= 0) {
            return json(['success' => false, 'reason' => 'Invalid article, language, or version.'], 400);
        }

        $articles = cms()->content(Article::class);
        $article = $articles->findVersion((int) $articleId, $lang->value, (int) $version);
        if ($article?->getVersion()?->getStatus() === ArticleStatus::PUBLISHED) {
            return json(['success' => false, 'reason' => 'Published versions cannot be deleted.'], 400);
        }

        return json([
            'success' => $articles->deleteVersion((int) $articleId, $lang->value, (int) $version),
        ]);
    }
}
