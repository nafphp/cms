<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\CMS\Content\Page\Block\BlockTypeInterface;
use Naf\CMS\Content\Page\Block\BlockTypeRegistry;
use Naf\CMS\Security\UrlPolicy;

use function Naf\CMS\cms;

final class PageBuilderPayloadNormalizer
{
    public const VERSION        = 1;
    public const MAX_JSON_BYTES = 262_144;
    public const MAX_BLOCKS     = 100;
    public const MAX_CHILDREN   = 24;
    public const MAX_DEPTH      = 4;

    private int $blockCount = 0;

    private ?BlockPlacementPolicy $policy = null;

    public function __construct(private ?BlockTypeRegistry $types = null)
    {
    }

    /**
     * Normalize a builder payload
     *
     * Without a policy only data validity is checked: known block types,
     * field values, URLs and size limits. That is what rendering uses. Saving
     * passes a BlockPlacementPolicy, which adds where blocks may go and who may
     * place them.
     *
     * @param array<string, mixed> $blocks
     * @param array<int, array<string, mixed>> $slots
     * @param array<int, string> $errors
     * @return array<string, array<string, mixed>>
     */
    public function normalize(array $blocks, array $slots, array &$errors = [], ?BlockPlacementPolicy $policy = null): array
    {
        $this->blockCount = 0;
        $this->policy     = $policy;
        $policy?->begin();
        $normalized = [];
        $slotMap    = [];
        foreach ($slots as $slot) {
            $id = trim((string) ($slot['id'] ?? ''));
            if ($id !== '') {
                $slotMap[$id] = $slot;
            }
        }

        foreach ($blocks as $id => $content) {
            $id = (string) $id;
            if (!isset($slotMap[$id])) {
                $errors[] = sprintf('Block "%s" is not part of the selected template.', $id);
                continue;
            }
            if ($content === null) {
                continue;
            }
            if (!is_array($content)) {
                $errors[] = sprintf('Block "%s" must be an object.', $id);
                continue;
            }

            $slot  = $slotMap[$id];
            $value = $this->normalizeBlock(
                $content,
                'blocks.' . $id,
                0,
                $errors,
                (string) ($slot['region'] ?? 'main'),
                BlockPlacementPolicy::acceptsList($slot['accepts'] ?? null),
                BlockPlacementPolicy::acceptsList($slot['nestedAccepts'] ?? null),
            );
            if ($value !== null) {
                $normalized[$id] = $value;
            }
        }

        $policy?->finish($errors);
        $this->policy = null;

        return $normalized;
    }

    /**
     * Normalize a single block for a preview, checking data validity only
     *
     * @param array<string, mixed> $content
     * @param array<int, string> $errors
     * @return array<string, mixed>|null
     */
    public function normalizeContent(array $content, array &$errors = []): ?array
    {
        $this->blockCount = 0;
        $this->policy     = null;

        return $this->normalizeBlock($content, 'block', 0, $errors, 'main', null, null);
    }

    /**
     * @param array<string, mixed> $content
     * @param array<int, string> $errors
     * @param array<int, string>|null $accepts       Types allowed at this position (policy only)
     * @param array<int, string>|null $nestedAccepts Types the slot allows anywhere inside its containers (policy only)
     * @return array<string, mixed>|null
     */
    private function normalizeBlock(
        array $content,
        string $path,
        int $depth,
        array &$errors,
        string $region,
        ?array $accepts,
        ?array $nestedAccepts,
    ): ?array {
        $this->blockCount++;
        if ($this->blockCount > self::MAX_BLOCKS) {
            $errors[] = sprintf('%s exceeds the limit of %d blocks.', $path, self::MAX_BLOCKS);

            return null;
        }
        if ($depth > self::MAX_DEPTH) {
            $errors[] = sprintf('%s exceeds the nesting limit of %d.', $path, self::MAX_DEPTH);

            return null;
        }

        foreach (array_keys($content) as $key) {
            if (!in_array((string) $key, ['type', 'label', 'previewTitle', 'props'], true)) {
                $errors[] = sprintf('%s contains unknown field "%s".', $path, (string) $key);
            }
        }

        $typeId = strtolower(trim((string) ($content['type'] ?? '')));
        $type   = $this->types()->get($typeId);
        if (!$type instanceof BlockTypeInterface) {
            $errors[] = sprintf('%s uses unknown block type "%s".', $path, $typeId);

            return null;
        }

        $props = $content['props'] ?? [];
        if (!is_array($props)) {
            $errors[] = sprintf('%s.props must be an object.', $path);

            return null;
        }

        $fields = [];
        foreach ($type->fields() as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name !== '') {
                $fields[$name] = $field;
            }
        }

        $normalizedProps = [];
        foreach ($props as $name => $value) {
            $name = (string) $name;
            if ($name === 'containerStyle' && $depth > 0) {
                if (!in_array($value, ['none', 'border', 'soft', 'card', 'accent'], true)) {
                    $errors[] = sprintf('%s.props.containerStyle has an invalid option.', $path);
                } else {
                    $normalizedProps[$name] = $value;
                }
                continue;
            }
            if (!isset($fields[$name])) {
                $errors[] = sprintf('%s.props contains unknown field "%s".', $path, $name);
                continue;
            }

            $normalizedValue = $this->normalizeField($fields[$name], $value, $path . '.props.' . $name, $depth, $errors, $nestedAccepts);
            if ($normalizedValue !== null) {
                $normalizedProps[$name] = $normalizedValue;
            }
        }

        $this->policy?->check($path, $typeId, $type, $normalizedProps, $region, $accepts, $depth === 0, $errors);

        return [
            'type'         => $typeId,
            'label'        => $this->shortText((string) ($content['label'] ?? $type->label()), 160),
            'previewTitle' => $this->shortText((string) ($content['previewTitle'] ?? $type->previewTitle()), 160),
            'props'        => $normalizedProps,
        ];
    }

    /**
     * @param array<string, mixed> $field
     * @param array<int, string> $errors
     * @param array<int, string>|null $nestedAccepts
     */
    private function normalizeField(array $field, mixed $value, string $path, int $depth, array &$errors, ?array $nestedAccepts = null): mixed
    {
        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'checkbox') {
            if (!is_bool($value)) {
                $errors[] = $path . ' must be a boolean.';

                return null;
            }

            return $value;
        }
        if ($type === 'number') {
            if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                $errors[] = $path . ' must be numeric.';

                return null;
            }
            $number = (float) $value;
            if (isset($field['min']) && $number < (float) $field['min'] || isset($field['max']) && $number > (float) $field['max']) {
                $errors[] = $path . ' is outside its allowed range.';

                return null;
            }

            return floor($number) === $number ? (int) $number : $number;
        }
        if ($type === 'select') {
            $allowed = array_map(static fn(mixed $option): string => is_array($option)
                ? (string) ($option['value'] ?? '')
                : (string) $option, is_array($field['options'] ?? null) ? $field['options'] : []);
            if ((string) ($field['name'] ?? '') === 'surface' || (string) ($field['label'] ?? '') === 'Surface') {
                $allowed = array_values(array_unique([...$allowed, ...$this->registeredSurfaceIds()]));
            }
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                $errors[] = $path . ' has an invalid option.';

                return null;
            }

            return $value;
        }
        if ($type === 'children') {
            if (!is_array($value)) {
                $errors[] = $path . ' must be an array.';

                return null;
            }
            if (count($value) > self::MAX_CHILDREN) {
                $errors[] = sprintf('%s exceeds the limit of %d children.', $path, self::MAX_CHILDREN);
            }
            $children = [];
            foreach (array_slice(array_values($value), 0, self::MAX_CHILDREN) as $index => $child) {
                if (!is_array($child)) {
                    $errors[] = sprintf('%s.%d must be an object.', $path, $index);
                    continue;
                }
                // Children are content inside a container: they are placed as
                // "main" content, limited by the slot's nestedAccepts and the
                // container field's own accepts.
                $normalized = $this->normalizeBlock(
                    $child,
                    $path . '.' . $index,
                    $depth + 1,
                    $errors,
                    'main',
                    BlockPlacementPolicy::narrowAccepts($nestedAccepts, BlockPlacementPolicy::acceptsList($field['accepts'] ?? null)),
                    $nestedAccepts,
                );
                if ($normalized !== null) {
                    $children[] = $normalized;
                }
            }

            return $children;
        }
        if ($type === 'stats') {
            return $this->normalizeRows($value, $path, ['value', 'label'], 12, $errors);
        }
        if ($type === 'metrics') {
            return $this->normalizeMetrics($value, $path, $errors);
        }
        if (!is_scalar($value) && $value !== null) {
            $errors[] = $path . ' must be text.';

            return null;
        }

        $string = trim((string) ($value ?? ''));
        $limit  = $type === 'textarea' ? 5_000 : 1_000;
        if (mb_strlen($string) > $limit) {
            $errors[] = sprintf('%s exceeds %d characters.', $path, $limit);

            return null;
        }

        $name = strtolower((string) ($field['name'] ?? ''));
        $url  = match (true) {
            $type === 'media'                          => UrlPolicy::media($string),
            in_array($name, ['url', 'embedurl'], true) => UrlPolicy::embed($string),
            str_ends_with($name, 'url')                => UrlPolicy::link($string),
            default                                    => $string,
        };
        if ($string !== '' && $url === null) {
            $errors[] = $path . ' contains an unsafe URL.';

            return null;
        }

        return $url ?? '';
    }

    /**
     * @param array<int, string> $keys
     * @param array<int, string> $errors
     * @return array<array-key, mixed>|null
     */
    private function normalizeRows(mixed $value, string $path, array $keys, int $limit, array &$errors): ?array
    {
        if (!is_array($value) || count($value) > $limit) {
            $errors[] = sprintf('%s must contain at most %d rows.', $path, $limit);

            return null;
        }
        $rows = [];
        foreach (array_values($value) as $index => $row) {
            if (!is_array($row) || array_diff(array_keys($row), $keys) !== []) {
                $errors[] = sprintf('%s.%d has invalid fields.', $path, $index);
                continue;
            }
            $rows[] = array_combine($keys, array_map(fn(string $key): string => $this->shortText((string) ($row[$key] ?? ''), 240), $keys));
        }

        return $rows;
    }

    /**
     * @param array<int, string> $errors
     * @return array<array-key, mixed>|null
     */
    private function normalizeMetrics(mixed $value, string $path, array &$errors): ?array
    {
        if (!is_array($value) || count($value) > 12) {
            $errors[] = $path . ' must contain at most 12 metrics.';

            return null;
        }
        $metrics = [];
        foreach (array_values($value) as $index => $metric) {
            if (!is_array($metric) || array_diff(array_keys($metric), ['label', 'value', 'color']) !== []) {
                $errors[] = sprintf('%s.%d has invalid fields.', $path, $index);
                continue;
            }
            $color = strtolower(trim((string) ($metric['color'] ?? '')));
            if (!is_numeric($metric['value'] ?? null) || preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
                $errors[] = sprintf('%s.%d has an invalid value or color.', $path, $index);
                continue;
            }
            $metrics[] = ['label' => $this->shortText((string) ($metric['label'] ?? ''), 240), 'value' => (float) $metric['value'], 'color' => $color];
        }

        return $metrics;
    }

    private function shortText(string $value, int $limit): string
    {
        return mb_substr(trim($value), 0, $limit);
    }

    private function types(): BlockTypeRegistry
    {
        return $this->types ??= cms()->blockTypes();
    }

    /** @return array<int, string> */
    private function registeredSurfaceIds(): array
    {
        $ids = [];
        foreach (cms()->themes()->all() as $theme) {
            $ids = [...$ids, ...array_keys($theme->surfaces())];
        }

        return array_values(array_unique(array_map('strval', $ids)));
    }
}
