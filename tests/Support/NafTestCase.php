<?php

declare(strict_types=1);

namespace Tests\Support;

use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Enums\Language;
use Naf\CMS\Models\Article;
use Naf\CMS\Models\ArticleVersion;
use PHPUnit\Framework\TestCase;

abstract class NafTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    protected function mockArticle(array $overrides = [], ?ArticleVersion $version = null): Article
    {
        $tags = $overrides['tags'] ?? null;
        unset($overrides['tags']);

        $data = array_replace([
            'id'                         => 1,
            'author'                     => null,
            'category_id'                => null,
            'hide_lead_image_in_content' => 1,
            'createdAt'                  => '2026-05-01 10:00:00',
        ], $overrides);

        $article = new Article($data);
        $article->setCurrentVersion($version ?? $this->mockArticleVersion([
            'article_id' => $data['id'],
        ]));

        if (is_array($tags)) {
            $article->setTags($tags);
        }

        return $article;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function mockArticleWithoutVersion(array $overrides = []): Article
    {
        $tags = $overrides['tags'] ?? null;
        unset($overrides['tags']);

        $article = new Article(array_replace([
            'id'                         => 1,
            'author'                     => null,
            'category_id'                => null,
            'hide_lead_image_in_content' => 1,
            'createdAt'                  => '2026-05-01 10:00:00',
        ], $overrides));

        if (is_array($tags)) {
            $article->setTags($tags);
        }

        return $article;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function mockArticleVersion(array $overrides = []): ArticleVersion
    {
        $data = array_replace([
            'id'         => 10,
            'article_id' => 1,
            'language'   => Language::DE->value,
            'version'    => 1,
            'status'     => ArticleStatus::DRAFT->value,
            'slug'       => 'test-article',
            'title'      => 'Test Article',
            'teaser'     => 'Short teaser',
            'content'    => $this->mockEditorContent([
                $this->mockParagraphBlock('Hello from NafCMS'),
            ]),
            'publishedAt' => null,
            'scheduledAt' => null,
            'show_teaser' => 1,
            'createdAt'   => '2026-05-01 10:30:00',
        ], $this->normalizeArticleVersionOverrides($overrides));

        return new ArticleVersion($data);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    protected function mockEditorContent(array $blocks = []): string
    {
        return json_encode(['blocks' => $blocks], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{type:string,data:array{text:string}}
     */
    protected function mockParagraphBlock(string $text): array
    {
        return [
            'type' => 'paragraph',
            'data' => ['text' => $text],
        ];
    }

    /**
     * @return array{type:string,data:array{file:array{url:string},caption:string}}
     */
    protected function mockImageBlock(string $url, string $caption = ''): array
    {
        return [
            'type' => 'image',
            'data' => [
                'file'    => ['url' => $url],
                'caption' => $caption,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function normalizeArticleVersionOverrides(array $overrides): array
    {
        if (($overrides['language'] ?? null) instanceof Language) {
            $overrides['language'] = $overrides['language']->value;
        }

        if (($overrides['status'] ?? null) instanceof ArticleStatus) {
            $overrides['status'] = $overrides['status']->value;
        }

        if (isset($overrides['content']) && is_array($overrides['content'])) {
            $overrides['content'] = $this->mockEditorContent($overrides['content']);
        }

        return $overrides;
    }
}
