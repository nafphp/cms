<?php

declare(strict_types=1);

namespace NixCMS\Page;

final class PageThemeMeta
{
    public function __construct(
        public ?string $accent = null,
        public ?string $headerBackground = null,
        public bool $headerBackgroundTransparent = true,
        public ?string $contentWidth = null,
        public bool $contentWidthIncludesChrome = true,
        public bool $contentWidthIncludesChromeBackground = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accent: isset($data['accent']) ? (string) $data['accent'] : null,
            headerBackground: isset($data['headerBackground']) ? (string) $data['headerBackground'] : null,
            headerBackgroundTransparent: filter_var($data['headerBackgroundTransparent'] ?? true, FILTER_VALIDATE_BOOLEAN),
            contentWidth: isset($data['contentWidth']) ? (string) $data['contentWidth'] : null,
            contentWidthIncludesChrome: filter_var($data['contentWidthIncludesChrome'] ?? true, FILTER_VALIDATE_BOOLEAN),
            contentWidthIncludesChromeBackground: filter_var($data['contentWidthIncludesChromeBackground'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
            'headerBackground' => $this->headerBackground,
            'headerBackgroundTransparent' => $this->headerBackgroundTransparent,
            'contentWidth' => $this->contentWidth,
            'contentWidthIncludesChrome' => $this->contentWidthIncludesChrome,
            'contentWidthIncludesChromeBackground' => $this->contentWidthIncludesChromeBackground,
        ];
    }
}
