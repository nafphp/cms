<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block;

final class BlockTypeRegistry
{
    /** @var array<string, BlockTypeInterface> */
    private array $types = [];

    public function add(BlockTypeInterface $type, bool $replace = false): self
    {
        $id = $this->normalize($type->id());
        if ($id === '') {
            throw new \InvalidArgumentException('Block type id cannot be empty.');
        }

        if (isset($this->types[$id]) && !$replace) {
            throw new \LogicException(sprintf('Block type "%s" is already registered.', $id));
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

    public function get(string $id): ?BlockTypeInterface
    {
        return $this->types[$this->normalize($id)] ?? null;
    }

    public function require(string $id): BlockTypeInterface
    {
        return $this->get($id) ?? throw new \RuntimeException(sprintf('Block type "%s" is not registered.', $id));
    }

    /**
     * @return array<string, BlockTypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function builderTypes(): array
    {
        return array_values(array_map(
            static fn(BlockTypeInterface $type): array => $type->toBuilderArray(),
            $this->types,
        ));
    }

    private function normalize(string $id): string
    {
        return strtolower(trim($id));
    }
}
