<?php

declare(strict_types=1);

namespace NixCMS\Models;

use NixCMS\Enums\ArticleStatus;
use NixCMS\Enums\Language;
use DateTime;
use NixPHP\ORM\Model\AbstractModel;

class ArticleVersion extends AbstractModel implements \JsonSerializable
{
    protected ?int $article_id = null;
    protected ?string $language = null;
    protected ?int $version = null;
    protected ?string $status = null;
    protected ?string $slug = null;
    protected ?string $title = null;
    protected ?string $teaser = null;
    protected ?string $content = null;
    protected ?string $publishedAt = null;
    protected ?string $scheduledAt = null;
    protected ?int $show_teaser = 0;
    protected ?string $createdAt = null;

    public string $table = 'article_versions';

    public function getTableName(bool $singular = false): string
    {
        return 'article_versions';
    }

    public function getArticleId(): ?int
    {
        return $this->article_id;
    }

    public function getLanguage(): ?Language
    {
        return $this->language !== null ? Language::tryFrom($this->language) : null;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getStatus(): ?ArticleStatus
    {
        return $this->status !== null ? ArticleStatus::tryFrom($this->status) : null;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getTeaser(): ?string
    {
        return $this->teaser;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getPublishedAt(): ?DateTime
    {
        if (trim((string) $this->publishedAt) === '') {
            return null;
        }

        try {
            return new DateTime((string) $this->publishedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getScheduledAt(): ?DateTime
    {
        if (trim((string) $this->scheduledAt) === '') {
            return null;
        }

        try {
            return new DateTime((string) $this->scheduledAt);
        } catch (\Throwable) {
            return null;
        }
    }

    public function shouldShowTeaser(): bool
    {
        return (int) ($this->show_teaser ?? 0) === 1;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function withTitle(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;
        return $clone;
    }

    public function withSlug(string $slug): self
    {
        $clone = clone $this;
        $clone->slug = $slug;
        return $clone;
    }

    public function withTeaser(string $teaser): self
    {
        $clone = clone $this;
        $clone->teaser = $teaser;
        return $clone;
    }

    public function withContent(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;
        return $clone;
    }

    public function withStatus(ArticleStatus $status): self
    {
        $clone = clone $this;
        $clone->status = $status->value;
        return $clone;
    }

    public function withLanguage(Language $language): self
    {
        $clone = clone $this;
        $clone->language = $language->value;
        return $clone;
    }

    public function withPublishedAt(?DateTime $publishedAt): self
    {
        $clone = clone $this;
        $clone->publishedAt = $publishedAt?->format('Y-m-d H:i:s');
        return $clone;
    }

    public function withScheduledAt(?DateTime $scheduledAt): self
    {
        $clone = clone $this;
        $clone->scheduledAt = $scheduledAt?->format('Y-m-d H:i:s');
        return $clone;
    }

    public function withShowTeaser(bool $showTeaser): self
    {
        $clone = clone $this;
        $clone->show_teaser = $showTeaser ? 1 : 0;
        return $clone;
    }

    public function jsonSerialize(): array
    {
        return [
            'articleVersionId' => $this->id ?? null,
            'language' => $this->language,
            'version' => $this->version,
            'slug' => $this->slug,
            'publishedAt' => $this->publishedAt,
            'scheduledAt' => $this->scheduledAt,
            'status' => $this->status,
            'title' => $this->title,
        ];
    }
}
