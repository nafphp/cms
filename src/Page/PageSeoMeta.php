<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

final class PageSeoMeta
{
    public function __construct(
        public string $description = '',
        public string $robots = 'index,follow',
        public ?string $canonicalUrl = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) ($data['description'] ?? ''),
            robots: (string) ($data['robots'] ?? 'index,follow'),
            canonicalUrl: isset($data['canonicalUrl']) ? (string) $data['canonicalUrl'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description'  => $this->description,
            'robots'       => $this->robots,
            'canonicalUrl' => $this->canonicalUrl,
        ];
    }
}
