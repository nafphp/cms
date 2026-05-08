<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use Closure;
use NixCMS\Localization\LanguageRegistry;
use NixCMS\Support\Slug;
use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use function NixCMS\cms;

final class ArticleDraftCreateTool implements ToolInterface, ScopedToolInterface
{
    /**
     * @param Closure(array<string, mixed>, ?int): array{articleId:int,articleVersionId:int}|null $createDraft
     */
    public function __construct(private ?Closure $createDraft = null) {}

    public function name(): string
    {
        return 'cms_article_draft_create';
    }

    public function description(): string
    {
        return 'Creates a new article draft with Editor.js-style content blocks.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('title', Schema::string()->description('Draft title.')->default(''))
            ->prop('slug', Schema::string()->description('Optional URL slug. Generated from title when empty.')->default(''))
            ->prop('language', Schema::string()->description('Article language code.')->default('en'))
            ->prop('teaser', Schema::string()->description('Optional teaser text.')->default(''))
            ->prop('body', Schema::string()->description('Plain text body. Used when blocks are omitted.')->default(''))
            ->prop('category', Schema::string()->description('Optional category name.')->default(''))
            ->prop('tags', Schema::string()->description('Optional comma-separated tag names.')->default(''))
            ->prop('author_id', Schema::integer()->description('Optional author user id.')->min(1))
            ->prop('show_teaser', Schema::boolean()->default(false))
            ->prop('hide_lead_image_in_content', Schema::boolean()->default(true))
            ->required('title')
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['articles:write'];
    }

    public function handle(array $args): array
    {
        $payload = $this->payload($args);
        $authorId = isset($args['author_id']) ? max(1, (int) $args['author_id']) : null;
        $result = $this->create($payload, $authorId);

        return [
            'success' => true,
            'articleId' => $result['articleId'],
            'articleVersionId' => $result['articleVersionId'],
            'status' => 'draft',
            'language' => $payload['language'],
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function payload(array $args): array
    {
        $title = trim((string) ($args['title'] ?? ''));
        if (mb_strlen($title) < 10) {
            throw new \InvalidArgumentException('Article title must contain at least 10 characters.');
        }

        $slug = Slug::fromString((string) ($args['slug'] ?? ''));
        if ($slug === '') {
            $slug = Slug::fromString($title);
        }

        if (mb_strlen($slug) < 5) {
            throw new \InvalidArgumentException('Article slug must contain at least 5 characters.');
        }

        $language = $this->normalizeLanguage((string) ($args['language'] ?? 'en'));
        $blocks = $this->blocks($args);
        if ($blocks === []) {
            throw new \InvalidArgumentException('Article draft content must contain at least one block or a non-empty body.');
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'teaser' => trim((string) ($args['teaser'] ?? '')),
            'language' => $language,
            'content' => ['blocks' => $blocks],
            'category' => trim((string) ($args['category'] ?? '')),
            'tags' => $this->tags($args['tags'] ?? []),
            'show_teaser' => filter_var($args['show_teaser'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'hide_lead_image_in_content' => filter_var($args['hide_lead_image_in_content'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'publish_date' => '',
        ];
    }

    private function normalizeLanguage(string $language): string
    {
        $registry = new LanguageRegistry();
        $normalized = $registry->normalize($language);
        if ($normalized === null || !$registry->isKnown($normalized)) {
            throw new \InvalidArgumentException('Unknown article language.');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $args
     * @return array<int, array<string, mixed>>
     */
    private function blocks(array $args): array
    {
        if (isset($args['blocks']) && is_array($args['blocks'])) {
            return array_values(array_filter(
                $args['blocks'],
                static fn(mixed $block): bool => is_array($block) && trim((string) ($block['type'] ?? '')) !== ''
            ));
        }

        $body = trim((string) ($args['body'] ?? ''));
        if ($body === '') {
            return [];
        }

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
     * @return array<int, string>
     */
    private function tags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[,;]+/', $tags) ?: [];
        }

        if (!is_array($tags)) {
            return [];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            $value = trim((string) $tag);
            if ($value === '') {
                continue;
            }

            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{articleId:int,articleVersionId:int}
     */
    private function create(array $payload, ?int $authorId): array
    {
        if ($this->createDraft !== null) {
            return ($this->createDraft)($payload, $authorId);
        }

        return cms()->articles()->create($payload, $authorId);
    }
}
