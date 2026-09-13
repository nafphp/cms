<?php

declare(strict_types=1);

namespace Naf\CMS\Mcp\Tools;

use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Enums\ToolRisk;

use Closure;
use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Models\Article;
use Naf\CMS\Models\ArticleVersion;
use Naf\MCP\Support\Schema;
use Naf\MCP\Tools\ScopedToolInterface;
use Naf\MCP\Tools\ToolInterface;
use Naf\CMS\Mcp\Tools\ArticleLookupTool;
use function Naf\CMS\cms;

final class ArticleDraftAppendTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK = ToolRisk::WRITE;
    private const bool AI_DESTRUCTIVE = false;
    /**
     * @param Closure(int, string): ?Article|null $findArticle
     * @param Closure(int, array<string, mixed>, ?int): array{articleId:int,articleVersionId:int}|null $saveDraft
     * @param Closure(string, string): ?int|null $resolveArticleId
     */
    public function __construct(
        private ?Closure $findArticle = null,
        private ?Closure $saveDraft = null,
        private ?Closure $resolveArticleId = null,
    ) {}

    public function name(): string
    {
        return 'cms_article_draft_append';
    }

    public function description(): string
    {
        return 'Appends plain text as paragraphs to an existing article draft, resolving the article by id, title, or slug.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('article_id', Schema::integer()->description('Preferred article id target.')->min(1))
            ->prop('reference', Schema::string()->description('Title or slug to resolve when article_id is omitted.')->default(''))
            ->prop('language', Schema::string()->description('Article language code.')->default('de'))
            ->prop('body', Schema::string()->description('Plain text to append as one or more paragraphs.'))
            ->prop('author_id', Schema::integer()->description('Optional author user id for audit/version metadata.')->min(1))
            ->required('body')
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['articles:write'];
    }

    public function handle(array $args): array
    {
        $language = strtolower(trim((string) ($args['language'] ?? 'de'))) ?: 'de';
        $articleId = $this->resolveTargetArticleId($args, $language);
        $body = trim((string) ($args['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('Body must not be empty.');
        }

        $article = $this->find($articleId, $language);
        if (!$article instanceof Article || !$article->getVersion() instanceof ArticleVersion) {
            throw new \RuntimeException('Article not found for append operation.');
        }

        $payload = $this->payload($article, $language, $body);
        $authorId = isset($args['author_id']) ? max(1, (int) $args['author_id']) : null;
        $result = $this->save($articleId, $payload, $authorId);

        return [
            'success' => true,
            'articleId' => $result['articleId'],
            'articleVersionId' => $result['articleVersionId'],
            'language' => $language,
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'appended_blocks' => count($payload['content']['blocks']) - count($this->existingBlocks($article)),
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    private function resolveTargetArticleId(array $args, string $language): int
    {
        $articleId = (int) ($args['article_id'] ?? 0);
        if ($articleId > 0) {
            return $articleId;
        }

        $reference = trim((string) ($args['reference'] ?? ''));
        if ($reference === '') {
            throw new \InvalidArgumentException('Provide either article_id or reference.');
        }

        $resolved = $this->resolveByReference($reference, $language);
        if ($resolved === null || $resolved <= 0) {
            throw new \RuntimeException('No article matched the given reference.');
        }

        return $resolved;
    }

    private function find(int $articleId, string $language): ?Article
    {
        if ($this->findArticle !== null) {
            return ($this->findArticle)($articleId, $language);
        }

        return cms()->articles()->find($articleId, $language);
    }

    private function resolveByReference(string $reference, string $language): ?int
    {
        if ($this->resolveArticleId !== null) {
            return ($this->resolveArticleId)($reference, $language);
        }

        $lookup = (new ArticleLookupTool())->handle([
            'query' => $reference,
            'language' => $language,
            'limit' => 5,
        ]);
        $matches = is_array($lookup['matches'] ?? null) ? $lookup['matches'] : [];
        if (count($matches) !== 1) {
            throw new \RuntimeException(count($matches) > 1
                ? 'Reference matched multiple articles; retry with article_id.'
                : 'No article matched the given reference.');
        }

        return (int) ($matches[0]['articleId'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Article $article, string $language, string $body): array
    {
        $version = $article->getVersion();
        $blocks = [
            ...$this->existingBlocks($article),
            ...$this->bodyBlocks($body),
        ];

        $payload = [
            'title' => $article->getTitle() ?? '',
            'slug' => $article->getSlug() ?? '',
            'teaser' => $article->getTeaser() ?? '',
            'language' => $language,
            'content' => ['blocks' => $blocks],
            'category' => $article->getCategory()?->getName() ?? '',
            'tags' => $article->getTagNames(),
            'show_teaser' => $article->shouldShowTeaser(),
            'hide_lead_image_in_content' => $article->shouldHideLeadImageInContent(),
            'publish_date' => $version?->getPublishedAt()?->format('Y-m-d H:i:s') ?? '',
        ];

        if ($version instanceof ArticleVersion && in_array($version->getStatus(), [ArticleStatus::DRAFT, ArticleStatus::DECLINED], true)) {
            $payload['article_version_id'] = $version->getId();
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function existingBlocks(Article $article): array
    {
        $content = $article->getContent();
        if ($content === null || trim($content) === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(
            is_array($decoded['blocks'] ?? null) ? $decoded['blocks'] : [],
            static fn(mixed $block): bool => is_array($block)
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bodyBlocks(string $body): array
    {
        $paragraphs = preg_split('/\R{2,}/', $body) ?: [];

        return array_values(array_map(
            static fn(string $paragraph): array => [
                'type' => 'paragraph',
                'data' => ['text' => nl2br(htmlspecialchars(trim($paragraph), ENT_QUOTES, 'UTF-8'), false)],
            ],
            array_filter($paragraphs, static fn(string $paragraph): bool => trim($paragraph) !== '')
        ));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{articleId:int,articleVersionId:int}
     */
    private function save(int $articleId, array $payload, ?int $authorId): array
    {
        if ($this->saveDraft !== null) {
            return ($this->saveDraft)($articleId, $payload, $authorId);
        }

        return cms()->articles()->draft($articleId, $payload, $authorId);
    }
}
