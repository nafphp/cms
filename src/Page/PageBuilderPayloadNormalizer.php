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

    public function __construct(private ?BlockTypeRegistry $types = null)
    {
    }

    /**
     * @param array<string, mixed> $blocks
     * @param array<int, array<string, mixed>> $slots
     * @param array<int, string> $errors
     * @return array<string, array<string, mixed>>
     */
    public function normalize(array $blocks, array $slots, array &$errors = []): array
    {
        $this->blockCount = 0;
        $normalized       = [];
        $slotMap          = [];
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

            $value = $this->normalizeBlock($content, $slotMap[$id], 'blocks.' . $id, 0, $errors);
            if ($value !== null) {
                $normalized[$id] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $slot
     * @param array<int, string> $errors
     * @return array<string, mixed>|null
     */
    private function normalizeBlock(array $content, array $slot, string $path, int $depth, array &$errors): ?array
    {
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

        $accepted = is_array($slot['accepts'] ?? null) ? array_map('strval', $slot['accepts']) : [];
        if ($accepted !== [] && !in_array($typeId, $accepted, true)) {
            $errors[] = sprintf('%s does not accept block type "%s".', $path, $typeId);
        }
        $region = (string) ($slot['region'] ?? 'main');
        if ($type->regions() !== [] && !in_array($region, $type->regions(), true)) {
            $errors[] = sprintf('%s cannot use block type "%s" in region "%s".', $path, $typeId, $region);
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

            $normalizedValue = $this->normalizeField($fields[$name], $value, $path . '.props.' . $name, $depth, $errors);
            if ($normalizedValue !== null) {
                $normalizedProps[$name] = $normalizedValue;
            }
        }

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
     */
    private function normalizeField(array $field, mixed $value, string $path, int $depth, array &$errors): mixed
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
                $normalized = $this->normalizeBlock($child, ['region' => 'main'], $path . '.' . $index, $depth + 1, $errors);
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
