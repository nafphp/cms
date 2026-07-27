<?php

declare(strict_types=1);

namespace NixPHP\CMS\Page;

final class PageThemeMeta
{
    public function __construct(
        public ?string $accent = null,
        public ?string $headerBackground = null,
        public bool $headerBackgroundTransparent = true,
        public ?string $footerBackground = null,
        public bool $footerBackgroundTransparent = false,
        public ?string $contentWidth = null,
        public bool $headerContentWidthIncludesChrome = true,
        public bool $headerBackgroundWidthIncludesChrome = false,
        public bool $footerContentWidthIncludesChrome = true,
        public bool $footerBackgroundWidthIncludesChrome = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accent: isset($data['accent']) ? (string) $data['accent'] : null,
            headerBackground: isset($data['headerBackground']) ? (string) $data['headerBackground'] : null,
            headerBackgroundTransparent: filter_var($data['headerBackgroundTransparent'] ?? true, FILTER_VALIDATE_BOOLEAN),
            footerBackground: isset($data['footerBackground']) ? (string) $data['footerBackground'] : null,
            footerBackgroundTransparent: filter_var($data['footerBackgroundTransparent'] ?? false, FILTER_VALIDATE_BOOLEAN),
            contentWidth: isset($data['contentWidth']) ? (string) $data['contentWidth'] : null,
            headerContentWidthIncludesChrome: filter_var($data['headerContentWidthIncludesChrome'] ?? true, FILTER_VALIDATE_BOOLEAN),
            headerBackgroundWidthIncludesChrome: filter_var($data['headerBackgroundWidthIncludesChrome'] ?? false, FILTER_VALIDATE_BOOLEAN),
            footerContentWidthIncludesChrome: filter_var($data['footerContentWidthIncludesChrome'] ?? true, FILTER_VALIDATE_BOOLEAN),
            footerBackgroundWidthIncludesChrome: filter_var($data['footerBackgroundWidthIncludesChrome'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
            'headerBackground' => $this->headerBackground,
            'headerBackgroundTransparent' => $this->headerBackgroundTransparent,
            'footerBackground' => $this->footerBackground,
            'footerBackgroundTransparent' => $this->footerBackgroundTransparent,
            'contentWidth' => $this->contentWidth,
            'headerContentWidthIncludesChrome' => $this->headerContentWidthIncludesChrome,
            'headerBackgroundWidthIncludesChrome' => $this->headerBackgroundWidthIncludesChrome,
            'footerContentWidthIncludesChrome' => $this->footerContentWidthIncludesChrome,
            'footerBackgroundWidthIncludesChrome' => $this->footerBackgroundWidthIncludesChrome,
        ];
    }
}
