<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Ai\ToolRouting;

use NixPHP\CMS\Services\Ai\ToolRouting\ToolContext;

final class ToolHardFilter
{
    /**
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    public function filter(array $tools, string $input, ToolContext $context): array
    {
        $query = strtolower($input . ' ' . implode(' ', array_filter($context->uiContext, 'is_scalar')));
        $writeIntent = $this->hasWriteIntent($query);
        $destructiveIntent = $this->hasDestructiveIntent($query);
        $requestedMode = $context->requestedMode();
        $requestedCapabilities = $context->requestedCapabilities();

        return array_values(array_filter($tools, function (array $tool) use ($context, $writeIntent, $destructiveIntent, $requestedMode, $requestedCapabilities): bool {
            $meta = $this->meta($tool);
            $risk = (string) ($meta['risk'] ?? 'read');
            $mode = (string) ($meta['mode'] ?? 'read');

            if ($risk === 'write' && !$writeIntent) {
                return false;
            }

            if (($meta['destructive'] ?? false) === true && !$destructiveIntent) {
                return false;
            }

            if ($requestedMode !== null && $mode !== $requestedMode) {
                return false;
            }

            if ($requestedCapabilities !== [] && !$this->intersects($this->capabilities($meta), $requestedCapabilities)) {
                return false;
            }

            return $this->areaAllowsTool($context, $meta, $risk);
        }));
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function areaAllowsTool(ToolContext $context, array $meta, string $risk): bool
    {
        if ($risk !== 'write') {
            return true;
        }

        $area = $context->normalizedArea();
        if ($area === '') {
            return ($meta['mode'] ?? 'read') !== 'write' && ($meta['destructive'] ?? false) !== true;
        }

        $capabilities = $this->capabilities($meta);

        return match ($area) {
            'pagebuilder' => $this->intersects($capabilities, ['block.create', 'block.update', 'layout.write', 'pages.write', 'content.write']),
            'themeeditor' => $this->intersects($capabilities, ['theme.write', 'themes.write']),
            'articleeditor' => $this->intersects($capabilities, ['content.write', 'articles.write']),
            'formeditor', 'formbuilder' => $this->intersects($capabilities, ['content.write', 'forms.write']),
            default => ($meta['mode'] ?? 'read') !== 'write' && ($meta['destructive'] ?? false) !== true,
        };
    }

    private function hasWriteIntent(string $query): bool
    {
        foreach (['create', 'add', 'append', 'write', 'update', 'delete', 'publish', 'commit', 'erstelle', 'füge', 'fuege', 'ergänze', 'aendere', 'ändere', 'speichere', 'loesche', 'lösche'] as $needle) {
            if (str_contains($query, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function hasDestructiveIntent(string $query): bool
    {
        foreach (['delete', 'remove', 'discard', 'rollback', 'archive', 'loesche', 'lösche', 'entferne', 'verwerfe'] as $needle) {
            if (str_contains($query, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $tool
     * @return array<string, mixed>
     */
    private function meta(array $tool): array
    {
        return is_array($tool['meta'] ?? null) ? $tool['meta'] : [];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<int, string>
     */
    private function capabilities(array $meta): array
    {
        return is_array($meta['capabilities'] ?? null) ? array_values(array_map('strval', $meta['capabilities'])) : [];
    }

    /**
     * @param array<int, string> $left
     * @param array<int, string> $right
     */
    private function intersects(array $left, array $right): bool
    {
        foreach ($right as $value) {
            if (in_array($value, $left, true)) {
                return true;
            }
        }

        return false;
    }
}
