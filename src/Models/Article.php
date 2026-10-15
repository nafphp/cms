<?php

declare(strict_types=1);

namespace Naf\CMS\Models;

use DateTime;
use JsonSerializable;
use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Enums\Language;
use Naf\CMS\Repositories\ArticleVersionRepository;
use Naf\CMS\Repositories\CategoryRepository;
use Naf\CMS\Repositories\TagRepository;
use Naf\CMS\Repositories\UserRepository;
use Naf\CMS\Security\UrlPolicy;
use Naf\ORM\Core\EntityInterface;
use Naf\ORM\Model\AbstractModel;

use function Naf\ORM\repo;

class Article extends AbstractModel implements JsonSerializable
{
    protected ?int $author                     = null;
    protected ?int $hide_lead_image_in_content = 1;
    protected ?int $category_id                = null;
    protected ?string $createdAt               = null;

    private ?ArticleVersion $currentVersion = null;
    /** @var array<int, string> */
    private array $tags = [];

    /** @var array<class-string<EntityInterface>, string> */
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
                static fn(string $tag): bool => $tag !== '',
            ));
        }

        return array_values(array_filter(
            array_map(
                static fn(Tag $tag): string => trim((string) ($tag->getName() ?? '')),
                $this->getTags(),
            ),
            static fn(string $name): bool => $name !== '',
        ));
    }

    /**
     * @return array<int, string>
     */
    public function getLanguages(): array
    {
        return array_values(array_filter(array_map(
            static fn(ArticleVersion $version): ?string => $version->getLanguageCode(),
            $this->getVersions(),
        )));
    }

    /** @return Category|null */
    public function getCategory(): Category|EntityInterface|null
    {
        if ($this->category_id === null) {
            return null;
        }

        $category = repo(CategoryRepository::class)->findOneBy('id', $this->category_id);

        return $category instanceof Category ? $category : null;
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

    public function getLanguageCode(): ?string
    {
        return $this->currentVersion?->getLanguageCode();
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

    public function getPublishedAt(): ?DateTime
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

        $data   = json_decode($content, true);
        $blocks = $data['blocks'] ?? [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) !== 'image') {
                continue;
            }

            $url = UrlPolicy::media((string) (
                $block['data']['file']['url']
                ?? $block['data']['file']['src']
                ?? $block['data']['url']
                ?? $block['data']['src']
                ?? ''
            ));

            if ($url !== null) {
                return $url;
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

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $author     = $this->getAuthor();
        $authorName = null;
        if ($author instanceof User) {
            $authorName = trim(((string) $author->getFirstname()) . ' ' . ((string) $author->getLastname()));
            if ($authorName === '') {
                $authorName = (string) $author->getUsername();
            }
        }

        return [
            'id'          => $this->id ?? null,
            'title'       => $this->getTitle(),
            'slug'        => $this->getSlug(),
            'teaser'      => $this->getTeaser(),
            'content'     => $this->getContent(),
            'status'      => $this->getStatus()?->value,
            'language'    => $this->getLanguageCode(),
            'author'      => $authorName,
            'category_id' => $this->getCategoryId(),
            'createdAt'   => $this->createdAt,
            'publishedAt' => $this->getPublishedAt()?->format('d.m.Y, H:i'),
            'languages'   => array_values(array_filter(array_map(
                static fn(string $lang): string => $lang,
                $this->getLanguages(),
            ))),
            'versions'               => $this->getVersions(),
            'lastVersion'            => $this->getVersion()?->getVersion(),
            'hideLeadImageInContent' => $this->shouldHideLeadImageInContent(),
            'tags'                   => $this->getTagNames(),
        ];
    }
}
