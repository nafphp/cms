<?php

declare(strict_types=1);

namespace Naf\CMS\Navigation;

use InvalidArgumentException;

final class Navigation
{
    /** @var array<int, NavigationItem> */
    private array $items      = [];
    private ?int $id          = null;
    private ?string $language = null;
    private string $status    = 'draft';

    private function __construct(
        private readonly string $identifier,
        private string $title,
        private string $scope = 'frontend',
    ) {
    }

    public static function create(string $identifier, ?string $title = null): self
    {
        $identifier = self::normalizeIdentifier($identifier);

        return new self($identifier, $title !== null && trim($title) !== '' ? trim($title) : self::titleFromIdentifier($identifier));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $navigation = self::create(
            (string) ($data['identifier'] ?? ''),
            isset($data['title']) ? (string) $data['title'] : null,
        );
        $navigation->id       = isset($data['id']) ? (int) $data['id'] : null;
        $navigation->scope    = trim((string) ($data['scope'] ?? 'frontend')) ?: 'frontend';
        $navigation->language = isset($data['language']) && trim((string) $data['language']) !== ''
            ? strtolower(trim((string) $data['language']))
            : null;
        $navigation->status = trim((string) ($data['status'] ?? 'draft')) ?: 'draft';

        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (is_array($item)) {
                $navigation->addItem(NavigationItem::fromArray($item));
            }
        }

        return $navigation;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id !== null && $id > 0 ? $id : null;

        return $this;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $title       = trim($title);
        $this->title = $title === '' ? self::titleFromIdentifier($this->identifier) : $title;

        return $this;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $scope       = trim($scope);
        $this->scope = $scope === '' ? 'frontend' : $scope;

        return $this;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $language       = $language === null ? null : strtolower(trim($language));
        $this->language = $language === '' ? null : $language;

        return $this;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $status       = trim($status);
        $this->status = $status === '' ? 'draft' : $status;

        return $this;
    }

    /**
     * @param NavigationItem|array<string, mixed> $item
     */
    public function addItem(NavigationItem|array $item): self
    {
        $item          = is_array($item) ? NavigationItem::fromArray($item) : $item;
        $this->items[] = $item;

        return $this;
    }

    /**
     * @return array<int, NavigationItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'identifier' => $this->identifier,
            'title'      => $this->title,
            'scope'      => $this->scope,
            'language'   => $this->language,
            'status'     => $this->status,
            'items'      => array_map(
                static fn(NavigationItem $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        $identifier = (string) preg_replace('/[^a-z0-9._-]+/', '-', $identifier);
        $identifier = trim($identifier, '.-_');

        if ($identifier === '') {
            throw new InvalidArgumentException('Navigation identifier cannot be empty.');
        }

        return $identifier;
    }

    private static function titleFromIdentifier(string $identifier): string
    {
        return ucwords(str_replace(['-', '_', '.'], ' ', $identifier));
    }
}
