<?php

declare(strict_types=1);

namespace Cms\Models;

use Cms\Enums\ArticleStatus;
use Cms\Enums\Language;
use Cms\Repositories\ArticleVersionRepository;
use Cms\Repositories\CategoryRepository;
use Cms\Repositories\TagRepository;
use Cms\Repositories\UserRepository;
use NixPHP\ORM\Core\EntityInterface;
use NixPHP\ORM\Model\AbstractModel;
use function NixPHP\ORM\repo;

class Article extends AbstractModel implements \JsonSerializable
{
    protected ?int $author = null;
    protected ?int $hide_lead_image_in_content = 1;
    protected ?int $category_id = null;
    protected ?string $createdAt = null;

    private ?ArticleVersion $currentVersion = null;
    private array $tags = [];

    public array $pivotTables = [
        Tag::class => 'article_tags',
    ];

    public function getVersion(): ?ArticleVersion
    {
        return $this->currentVersion;
    }

    /**
     * @internal
     */
    public function setCurrentVersion(ArticleVersion $articleVersion): void
    {
        $this->currentVersion = $articleVersion;
    }

    public function getAuthor(): User|EntityInterface|null
    {
        if ($this->author === null) {
            return null;
        }

        return repo(UserRepository::class)->findOneBy('id', $this->author);
    }

    /**
     * @return array<int, ArticleVersion>
     */
    public function getVersions(): array
    {
        return repo(ArticleVersionRepository::class)
            ->findBy(['article_id' => $this->id], orderBy: ['version' => 'DESC']);
    }

    /**
     * @return array<int, Tag>
     */
    public function getTags(): array
    {
        return repo(TagRepository::class)->findByPivot(Article::class, (int) $this->id);
    }

    /**
     * @return array<int, string>
     */
    public function getTagNames(): array
    {
        if ($this->tags !== []) {
            return array_values(array_filter(
                array_map(static fn(mixed $tag): string => trim((string) $tag), $this->tags),
                static fn(string $tag): bool => $tag !== ''
            ));
        }

        return array_values(array_filter(
            array_map(
                static fn(Tag $tag): string => trim((string) ($tag->getName() ?? '')),
                $this->getTags()
            ),
            static fn(string $name): bool => $name !== ''
        ));
    }

    /**
     * @return array<int, ?Language>
     */
    public function getLanguages(): array
    {
        return array_map(
            static fn(ArticleVersion $version): ?Language => $version->getLanguage(),
            $this->getVersions()
        );
    }

    public function getCategory(): Category|EntityInterface|null
    {
        if ($this->category_id === null) {
            return null;
        }

        return repo(CategoryRepository::class)->findOneBy('id', $this->category_id);
    }

    public function getCategoryId(): ?int
    {
        return $this->category_id;
    }

    public function shouldHideLeadImageInContent(): bool
    {
        return (int) ($this->hide_lead_image_in_content ?? 1) === 1;
    }

    public function setHideLeadImageInContent(bool $hideLeadImageInContent): void
    {
        $this->hide_lead_image_in_content = $hideLeadImageInContent ? 1 : 0;
    }

    public function getLanguage(): ?Language
    {
        return $this->currentVersion?->getLanguage();
    }

    public function getSlug(): ?string
    {
        return $this->currentVersion?->getSlug();
    }

    public function getTitle(): ?string
    {
        return $this->currentVersion?->getTitle();
    }

    public function getTeaser(): ?string
    {
        return $this->currentVersion?->getTeaser();
    }

    public function shouldShowTeaser(): bool
    {
        return $this->currentVersion?->shouldShowTeaser() ?? false;
    }

    public function getContent(): ?string
    {
        return $this->currentVersion?->getContent();
    }

    public function getStatus(): ?ArticleStatus
    {
        return $this->currentVersion?->getStatus();
    }

    public function getPublishedAt(): ?\DateTime
    {
        return $this->currentVersion?->getPublishedAt();
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getImage(): ?string
    {
        $content = $this->currentVersion?->getContent();
        if (!$content) {
            return null;
        }

        $data = json_decode($content, true);
        $blocks = $data['blocks'] ?? [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) !== 'image') {
                continue;
            }

            $url = trim((string) (
                $block['data']['file']['url']
                ?? $block['data']['file']['src']
                ?? $block['data']['url']
                ?? $block['data']['src']
                ?? ''
            ));

            if ($url !== '') {
                return htmlspecialchars($url);
            }
        }

        return null;
    }

    public function getEstimatedReadingTime(): int
    {
        $content = $this->getContent();
        if ($content === null) {
            return 0;
        }

        $words = str_word_count(strip_tags($content));
        return (int) ceil($words / 200);
    }

    public function setCategory(?Category $category): void
    {
        $this->category_id = $category?->getId();
    }

    /**
     * @param array<int, string|Tag> $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function jsonSerialize(): array
    {
        $author = $this->getAuthor();
        $authorName = null;
        if ($author instanceof User) {
            $authorName = trim(((string) $author->getFirstname()) . ' ' . ((string) $author->getLastname()));
            if ($authorName === '') {
                $authorName = (string) $author->getUsername();
            }
        }

        return [
            'id' => $this->id ?? null,
            'title' => $this->getTitle(),
            'slug' => $this->getSlug(),
            'teaser' => $this->getTeaser(),
            'content' => $this->getContent(),
            'status' => $this->getStatus()?->value,
            'language' => $this->getLanguage()?->value,
            'author' => $authorName,
            'createdAt' => $this->createdAt,
            'publishedAt' => $this->getPublishedAt()?->format('d.m.Y, H:i'),
            'languages' => array_values(array_filter(array_map(
                static fn(?Language $lang): ?string => $lang?->value,
                $this->getLanguages()
            ))),
            'versions' => $this->getVersions(),
            'lastVersion' => $this->getVersion()?->getVersion(),
            'hideLeadImageInContent' => $this->shouldHideLeadImageInContent(),
            'tags' => $this->getTagNames(),
        ];
    }
}
