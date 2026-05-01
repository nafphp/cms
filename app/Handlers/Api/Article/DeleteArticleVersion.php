<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Article;

use NixCMS\Enums\ArticleStatus;
use NixCMS\Enums\Language;
use NixCMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class DeleteArticleVersion
{
    public function __invoke(string $articleId, string $language, string $version): ResponseInterface
    {
        $lang = Language::tryFrom(strtolower(trim($language)));
        if (!$lang instanceof Language || (int) $articleId <= 0 || (int) $version <= 0) {
            return json(['success' => false, 'reason' => 'Invalid article, language, or version.'], 400);
        }

        $articles = \cms()->content(Article::class);
        $article = $articles->findVersion((int) $articleId, $lang->value, (int) $version);
        if ($article?->getVersion()?->getStatus() === ArticleStatus::PUBLISHED) {
            return json(['success' => false, 'reason' => 'Published versions cannot be deleted.'], 400);
        }

        return json([
            'success' => $articles->deleteVersion((int) $articleId, $lang->value, (int) $version),
        ]);
    }
}
