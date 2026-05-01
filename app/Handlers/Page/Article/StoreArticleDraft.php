<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Page\Article;

use NixCMS\Enums\Language;
use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class StoreArticleDraft
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $errors = $this->validate($data);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        try {
            $articleId = (int) ($data['article_id'] ?? 0);
            $authorId = \user()?->get()?->getId();
            $articles = \cms()->content(Article::class);

            $result = $articleId > 0
                ? $articles->draft($articleId, $data, $authorId)
                : $articles->create($data, $authorId);

            return json([
                'success' => true,
                'articleId' => $result['articleId'],
                'articleVersionId' => $result['articleVersionId'],
            ]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    private function validate(array $data): array
    {
        $errors = [];
        if (mb_strlen(trim((string) ($data['title'] ?? ''))) < 10) {
            $errors['title'][] = 'Please enter a title with at least 10 characters.';
        }
        if (mb_strlen(trim((string) ($data['slug'] ?? ''))) < 5) {
            $errors['slug'][] = 'Please enter a slug with at least 5 characters.';
        }
        $teaser = trim((string) ($data['teaser'] ?? ''));
        if ($teaser !== '' && mb_strlen($teaser) < 60) {
            $errors['teaser'][] = 'Please enter a teaser with at least 60 characters.';
        }
        if (Language::tryFrom(strtolower(trim((string) ($data['language'] ?? '')))) === null) {
            $errors['language'][] = 'Please choose a valid language.';
        }
        if (!isset($data['content']) || !is_array($data['content'])) {
            $errors['content'][] = 'Please add article content.';
        }

        return $errors;
    }
}
