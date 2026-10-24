<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\CMS\Content\Page\Block\BlockTypeInterface;
use Naf\CMS\Content\Page\Block\BlockTypeRegistry;
use Naf\CMS\Support\UserHelper;

use function Naf\CMS\cms;

/**
 * The rules for placing blocks when a page is saved
 *
 * The payload normalizer always checks that blocks are valid data. These rules
 * decide where a valid block may go and who may put it there, and they apply
 * only when a page is saved, never while it is rendered: narrowing the
 * builder must not make content disappear from pages that are already live.
 *
 * A block that was already stored at the same position with the same type is
 * kept as it is ("grandfathered") for the placement rules, so existing pages
 * stay editable after the rules were tightened. The permission rule is stricter:
 * an editor without the permission may keep such a block only unchanged.
 * Moving a block counts as placing it anew.
 *
 * Use the page-builder:audit command to find stored pages that break the
 * current rules.
 *
 * @internal
 */
final class BlockPlacementPolicy
{
    /** @var array<string, array{type: string, props: array<string, mixed>}> */
    private array $existing = [];

    /** @var array<string, array<int, array{path: string, kept: bool}>> */
    private array $uniqueOccurrences = [];

    /**
     * @param array<string, mixed> $existingBlocks The stored blocks of the page being saved
     */
    public function __construct(
        private readonly BlockTypeRegistry $types,
        private readonly ?UserHelper $actor,
        private readonly string $templateKind = 'static',
        private readonly string $templateContentType = '',
        private readonly bool $customLayout = false,
        array $existingBlocks = [],
        private readonly bool $checkPermissions = true,
    ) {
        foreach ($existingBlocks as $slotId => $content) {
            if (is_array($content)) {
                $this->indexExisting($content, 'blocks.' . $slotId);
            }
        }
    }

    /**
     * @param array<string, mixed> $template       Template definition (kind, contentType)
     * @param array<string, mixed> $existingBlocks The stored blocks of the page being saved
     */
    public static function forTemplate(
        array $template,
        ?UserHelper $actor,
        array $existingBlocks = [],
        bool $customLayout = false,
        bool $checkPermissions = true,
        ?BlockTypeRegistry $types = null,
    ): self {
        return new self(
            $types ?? cms()->blockTypes(),
            $actor,
            (string) ($template['kind'] ?? 'static'),
            (string) ($template['contentType'] ?? ''),
            $customLayout || (string) ($template['kind'] ?? 'static') === 'custom',
            $existingBlocks,
            $checkPermissions,
        );
    }

    /**
     * Start a new payload; forgets the unique block types seen so far
     */
    public function begin(): void
    {
        $this->uniqueOccurrences = [];
    }

    /**
     * Report unique block types that appear more than once in the payload
     *
     * Runs after the whole payload was checked, so the result does not depend
     * on the order of the slots. Blocks that were already stored are not
     * reported; every newly placed extra occurrence is.
     *
     * @param array<int, string> $errors
     */
    public function finish(array &$errors): void
    {
        foreach ($this->uniqueOccurrences as $typeId => $occurrences) {
            if (count($occurrences) < 2) {
                continue;
            }
            foreach ($occurrences as $occurrence) {
                if (!$occurrence['kept']) {
                    $errors[] = sprintf('%s uses block type "%s", which may only appear once per page.', $occurrence['path'], $typeId);
                }
            }
        }

        $this->uniqueOccurrences = [];
    }

    /**
     * Check one normalized block against the placement rules
     *
     * @param array<string, mixed>   $props   The block's normalized props
     * @param array<int, string>|null $accepts Types allowed at this position; null for no restriction
     * @param array<int, string>     $errors
     */
    public function check(
        string $path,
        string $typeId,
        BlockTypeInterface $type,
        array $props,
        string $region,
        ?array $accepts,
        bool $topLevel,
        array &$errors,
    ): void {
        $previous    = $this->existing[$path] ?? null;
        $kept        = $previous !== null && $previous['type'] === $typeId;
        $keptAsIs    = $kept && $previous['props'] === $props;
        $blockErrors = [];

        if (!$kept) {
            if (!$this->types->isAvailable($typeId)) {
                $blockErrors[] = sprintf('%s uses block type "%s", which is not available in the page builder.', $path, $typeId);
            }
            if ($accepts !== null && !in_array($typeId, $accepts, true)) {
                $blockErrors[] = sprintf('%s does not accept block type "%s".', $path, $typeId);
            }
            if ($type->regions() !== [] && !in_array($region, $type->regions(), true)) {
                $blockErrors[] = sprintf('%s cannot use block type "%s" in region "%s".', $path, $typeId, $region);
            }
            if ($this->customLayout && $topLevel && in_array($region, ['header', 'footer'], true)
                && !in_array($region, $this->types->chromeRegions($typeId), true)) {
                $blockErrors[] = sprintf('%s cannot use block type "%s" in the %s area of a custom layout.', $path, $typeId, $region);
            }
            if ($type->templateKinds() !== [] && !in_array($this->templateKind, $type->templateKinds(), true)) {
                $blockErrors[] = sprintf('%s cannot use block type "%s" in a "%s" template.', $path, $typeId, $this->templateKind);
            }
            if ($type->contentTypes() !== [] && !in_array($this->templateContentType, $type->contentTypes(), true)) {
                $blockErrors[] = sprintf('%s cannot use block type "%s" in a template for this content type.', $path, $typeId);
            }
        }

        if ($type->unique()) {
            $this->uniqueOccurrences[$typeId][] = ['path' => $path, 'kept' => $kept];
        }

        if ($this->checkPermissions && !$keptAsIs && !$this->types->isPermitted($typeId, $this->actor)) {
            $blockErrors[] = sprintf('%s: you are not allowed to add or change blocks of type "%s".', $path, $typeId);
        }

        array_push($errors, ...$blockErrors);
    }

    /**
     * Combine the types allowed by the surrounding slot and by a container field
     *
     * @param array<int, string>|null $outer
     * @param array<int, string>|null $inner
     * @return array<int, string>|null
     */
    public static function narrowAccepts(?array $outer, ?array $inner): ?array
    {
        if ($outer === null) {
            return $inner;
        }
        if ($inner === null) {
            return $outer;
        }

        return array_values(array_intersect($outer, $inner));
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<int, string>|null
     */
    public static function acceptsList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $list = array_values(array_unique(array_filter(array_map(
            static fn(mixed $type): string => is_scalar($type) ? strtolower(trim((string) $type)) : '',
            $value,
        ))));

        return $list === [] ? null : $list;
    }

    /**
     * @param array<string, mixed> $content
     */
    private function indexExisting(array $content, string $path): void
    {
        $type  = strtolower(trim((string) ($content['type'] ?? '')));
        $props = is_array($content['props'] ?? null) ? $content['props'] : [];
        if ($type === '') {
            return;
        }

        $this->existing[$path] = ['type' => $type, 'props' => $props];

        // Child blocks are lists of block objects inside a prop; the paths match
        // the ones the normalizer builds, so a block keeps its identity by place.
        foreach ($props as $name => $value) {
            if (!is_array($value) || !array_is_list($value)) {
                continue;
            }
            foreach ($value as $index => $child) {
                if (is_array($child) && isset($child['type'])) {
                    $this->indexExisting($child, $path . '.props.' . $name . '.' . $index);
                }
            }
        }
    }
}
