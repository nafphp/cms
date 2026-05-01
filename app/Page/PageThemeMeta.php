<?php

declare(strict_types=1);

namespace NixCMS\Page;

final class PageThemeMeta
{
    public function __construct(
        public ?string $accent = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accent: isset($data['accent']) ? (string) $data['accent'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
        ];
    }
}
