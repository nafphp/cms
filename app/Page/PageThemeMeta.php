<?php

declare(strict_types=1);

namespace NixCMS\Page;

final class PageThemeMeta
{
    public function __construct(
        public ?string $accent = null,
        public ?string $contentWidth = null,
        public bool $contentWidthIncludesChrome = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accent: isset($data['accent']) ? (string) $data['accent'] : null,
            contentWidth: isset($data['contentWidth']) ? (string) $data['contentWidth'] : null,
            contentWidthIncludesChrome: filter_var($data['contentWidthIncludesChrome'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
            'contentWidth' => $this->contentWidth,
            'contentWidthIncludesChrome' => $this->contentWidthIncludesChrome,
        ];
    }
}
