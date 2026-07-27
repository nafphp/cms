<?php

declare(strict_types=1);

namespace NixPHP\CMS\Page;

use NixPHP\CMS\Page\PageNavigationMeta;
use NixPHP\CMS\Page\PageSeoMeta;
use NixPHP\CMS\Page\PageThemeMeta;

final class PageMeta
{
    public function __construct(
        public PageSeoMeta $seo = new PageSeoMeta(),
        public PageNavigationMeta $navigation = new PageNavigationMeta(),
        public PageThemeMeta $theme = new PageThemeMeta(),
        public array $extra = [],
        public int $version = 1,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            seo: PageSeoMeta::fromArray(self::arrayValue($data['seo'] ?? [])),
            navigation: PageNavigationMeta::fromArray(self::arrayValue($data['navigation'] ?? [])),
            theme: PageThemeMeta::fromArray(self::arrayValue($data['theme'] ?? [])),
            extra: self::arrayValue($data['extra'] ?? []),
            version: (int) ($data['version'] ?? 1),
        );
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'seo' => $this->seo->toArray(),
            'navigation' => $this->navigation->toArray(),
            'theme' => $this->theme->toArray(),
            'extra' => $this->extra,
        ];
    }

    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
