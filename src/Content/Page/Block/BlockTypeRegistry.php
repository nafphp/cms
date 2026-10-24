<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block;

use InvalidArgumentException;
use LogicException;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Support\UserHelper;
use RuntimeException;
use Throwable;

use function Naf\config;
use function Naf\log;

/**
 * Page-builder block types and which of them editors may use
 *
 * Registering a type makes it renderable. Whether editors may place it is a
 * separate decision, so that narrowing the builder never breaks pages that
 * already contain a block:
 *
 * - only()/disable() (or `cms:page_builder:allowed_block_types` and
 *   `cms:page_builder:disabled_block_types`) take a type out of the builder.
 *   Existing blocks of that type keep rendering and can still be edited.
 * - restrict() (or `cms:page_builder:block_permissions`, or the type's own
 *   RestrictedBlockTypeInterface) lets only editors with a permission add or
 *   change blocks of that type.
 * - remove() unregisters a type completely; its blocks no longer render.
 *
 * @api
 */
final class BlockTypeRegistry
{
    /** @var array<string, BlockTypeInterface> */
    private array $types = [];

    /** @var array<int, string>|null Null means every registered type is allowed */
    private ?array $allowed = null;

    /** @var array<string, bool> True disables, false re-enables a type the configuration disabled */
    private array $disabled = [];

    /** @var array<string, Permission|null> Null lifts the requirement */
    private array $permissions = [];

    /** @var array<string, true> */
    private array $reportedPermissionValues = [];

    public function add(BlockTypeInterface $type, bool $replace = false): self
    {
        $id = $this->normalize($type->id());
        if ($id === '') {
            throw new InvalidArgumentException('Block type id cannot be empty.');
        }

        if (isset($this->types[$id]) && !$replace) {
            throw new LogicException(sprintf('Block type "%s" is already registered.', $id));
        }

        $this->types[$id] = $type;

        return $this;
    }

    /**
     * @param iterable<BlockTypeInterface> $types
     */
    public function addMany(iterable $types, bool $replace = false): self
    {
        foreach ($types as $type) {
            $this->add($type, $replace);
        }

        return $this;
    }

    /**
     * Unregister a type completely
     *
     * Its blocks no longer render on existing pages. To only keep editors from
     * adding it, use disable() or only().
     */
    public function remove(string $id): self
    {
        unset($this->types[$this->normalize($id)]);

        return $this;
    }

    public function get(string $id): ?BlockTypeInterface
    {
        return $this->types[$this->normalize($id)] ?? null;
    }

    public function require(string $id): BlockTypeInterface
    {
        return $this->get($id) ?? throw new RuntimeException(sprintf('Block type "%s" is not registered.', $id));
    }

    /**
     * @return array<string, BlockTypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Offer only these types in the page builder
     *
     * Replaces an earlier only() and the configured allowlist. Types registered
     * later, by plugins, are not offered unless they are listed.
     */
    public function only(string ...$ids): self
    {
        $this->allowed = array_values(array_unique(array_filter(array_map($this->normalize(...), $ids))));

        return $this;
    }

    /**
     * Take types out of the page builder; existing blocks keep rendering
     */
    public function disable(string ...$ids): self
    {
        foreach ($ids as $id) {
            $this->disabled[$this->normalize($id)] = true;
        }

        return $this;
    }

    /**
     * Undo disable(), including a type the configuration disables
     *
     * An allowlist still applies: a type missing from only() stays unavailable.
     */
    public function enable(string ...$ids): self
    {
        foreach ($ids as $id) {
            $this->disabled[$this->normalize($id)] = false;
        }

        return $this;
    }

    /**
     * Require a permission to add or change blocks of a type
     *
     * Overrides the configuration and the type's own requirement; null lifts it.
     */
    public function restrict(string $id, ?Permission $permission): self
    {
        $this->permissions[$this->normalize($id)] = $permission;

        return $this;
    }

    /**
     * Whether editors may place this type at all, independent of who they are
     */
    public function isAvailable(string $id): bool
    {
        $id = $this->normalize($id);
        if (!isset($this->types[$id])) {
            return false;
        }

        $allowed = $this->allowedTypes();
        if ($allowed !== null && !in_array($id, $allowed, true)) {
            return false;
        }

        return !($this->disabled[$id] ?? in_array($id, $this->configuredList('disabled_block_types'), true));
    }

    /**
     * The permission needed to add or change blocks of this type
     *
     * Also null for a configured value that is not a known permission; such a
     * type is closed to everyone, which isPermitted() reports.
     */
    public function requiredPermission(string $id): ?Permission
    {
        $rule = $this->permissionRule($this->normalize($id));

        return $rule === false ? null : $rule;
    }

    /**
     * Whether this actor may add or change blocks of this type
     *
     * A configured permission that does not exist denies everyone, so a typo
     * cannot silently open a restricted block.
     */
    public function isPermitted(string $id, ?UserHelper $actor): bool
    {
        $rule = $this->permissionRule($this->normalize($id));
        if ($rule === null) {
            return true;
        }
        if ($rule === false || !$actor instanceof UserHelper) {
            return false;
        }

        return $actor->can($rule);
    }

    /**
     * Whether this actor may add a new block of this type
     */
    public function isInsertable(string $id, ?UserHelper $actor): bool
    {
        return $this->isAvailable($id) && $this->isPermitted($id, $actor);
    }

    /**
     * Whether blocks of this type hold child blocks (a field of type "children")
     */
    public function isContainer(string $id): bool
    {
        $type = $this->get($id);
        if (!$type instanceof BlockTypeInterface) {
            return false;
        }

        foreach ($type->fields() as $field) {
            if (($field['type'] ?? null) === 'children') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string> The custom-layout areas ("header", "footer") this type may fill
     */
    public function chromeRegions(string $id): array
    {
        $type = $this->get($id);

        return $type instanceof ChromeBlockTypeInterface
            ? array_values(array_intersect(['header', 'footer'], array_map('strval', $type->chromeRegions())))
            : [];
    }

    /**
     * Builder definitions of every registered type
     *
     * Types the actor may not add are included, so blocks that already exist
     * on a page can still be shown; `insertable` and `editable` tell the
     * builder what to offer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function builderTypes(?UserHelper $actor = null): array
    {
        $types = [];
        foreach ($this->types as $id => $type) {
            $types[] = [
                ...$type->toBuilderArray(),
                'insertable'    => $this->isInsertable($id, $actor),
                'editable'      => $this->isPermitted($id, $actor),
                'container'     => $this->isContainer($id),
                'chromeRegions' => $this->chromeRegions($id),
            ];
        }

        return $types;
    }

    /**
     * @return array<int, string>|null
     */
    private function allowedTypes(): ?array
    {
        if ($this->allowed !== null) {
            return $this->allowed;
        }

        // An empty configured list means "not configured": configuration
        // merges cannot tell an empty allowlist from a missing one.
        $configured = $this->configuredList('allowed_block_types');

        return $configured === [] ? null : $configured;
    }

    /**
     * Null: no requirement. False: misconfigured, nobody may use the type.
     */
    private function permissionRule(string $id): Permission|false|null
    {
        if (array_key_exists($id, $this->permissions)) {
            return $this->permissions[$id];
        }

        $configured = $this->configuredMap('block_permissions');
        if (array_key_exists($id, $configured)) {
            $value      = $configured[$id];
            $permission = $value instanceof Permission ? $value : (is_string($value) ? Permission::tryFrom($value) : null);
            if ($permission === null && $value !== null && $value !== '') {
                if (!isset($this->reportedPermissionValues[$id])) {
                    $this->reportedPermissionValues[$id] = true;
                    log()->warning('Unknown permission configured for page block type; nobody may use it', [
                        'type'       => $id,
                        'permission' => is_scalar($value) ? (string) $value : get_debug_type($value),
                    ]);
                }

                return false;
            }

            return $permission;
        }

        $type = $this->types[$id] ?? null;

        return $type instanceof RestrictedBlockTypeInterface ? $type->requiredPermission() : null;
    }

    /**
     * @return array<int, string>
     */
    private function configuredList(string $key): array
    {
        $value = $this->configured($key);

        return is_array($value)
            ? array_values(array_unique(array_filter(array_map(
                fn(mixed $id): string => is_scalar($id) ? $this->normalize((string) $id) : '',
                $value,
            ))))
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredMap(string $key): array
    {
        $value  = $this->configured($key);
        $result = [];
        foreach (is_array($value) ? $value : [] as $id => $permission) {
            if (is_string($id) && $this->normalize($id) !== '') {
                $result[$this->normalize($id)] = $permission;
            }
        }

        return $result;
    }

    private function configured(string $key): mixed
    {
        try {
            return config('cms:page_builder:' . $key);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalize(string $id): string
    {
        return strtolower(trim($id));
    }
}
