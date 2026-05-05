<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai\ToolRouting;

final readonly class ToolContext
{
    /**
     * @param array<string, mixed> $uiContext
     */
    public function __construct(
        public string $area = '',
        public ?string $pageId = null,
        public ?string $layoutId = null,
        public ?string $themeId = null,
        public ?string $contentType = null,
        public array $uiContext = [],
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public static function fromArray(array $context): self
    {
        return new self(
            area: self::stringValue($context['area'] ?? ''),
            pageId: self::nullableString($context['pageId'] ?? $context['page_id'] ?? null),
            layoutId: self::nullableString($context['layoutId'] ?? $context['layout_id'] ?? null),
            themeId: self::nullableString($context['themeId'] ?? $context['theme_id'] ?? $context['theme'] ?? null),
            contentType: self::nullableString($context['contentType'] ?? $context['content_type'] ?? null),
            uiContext: $context,
        );
    }

    public function normalizedArea(): string
    {
        return strtolower(str_replace(['-', '_'], '', $this->area));
    }

    /**
     * @return array<int, string>
     */
    public function requestedCapabilities(): array
    {
        $capabilities = $this->uiContext['capabilities'] ?? $this->uiContext['requestedCapabilities'] ?? [];
        if (!is_array($capabilities)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn(mixed $value): string => trim((string) $value), $capabilities),
            static fn(string $value): bool => $value !== '',
        )));
    }

    public function requestedMode(): ?string
    {
        $mode = self::nullableString($this->uiContext['mode'] ?? null);

        return $mode !== null ? strtolower($mode) : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function stringValue(mixed $value): string
    {
        return self::nullableString($value) ?? '';
    }
}
