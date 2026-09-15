<?php

declare(strict_types=1);

namespace Naf\CMS\Content;

use InvalidArgumentException;
use LogicException;
use Naf\ORM\Core\EntityInterface;
use RuntimeException;

final class ContentRegistry
{
    /** @var array<string, ContentTypeInterface> */
    private array $types = [];

    public function add(ContentTypeInterface $type, bool $replace = false): self
    {
        if (isset($this->types[$type->id()]) && !$replace) {
            throw new LogicException(sprintf('Content type "%s" is already registered.', $type->id()));
        }

        $this->types[$type->id()] = $type;

        return $this;
    }

    /** @param iterable<ContentTypeInterface> $types */
    public function addMany(iterable $types, bool $replace = false): self
    {
        foreach ($types as $type) {
            $this->add($type, $replace);
        }

        return $this;
    }

    public function get(string $id): ?ContentTypeInterface
    {
        $id = strtolower(trim($id));
        foreach ($this->types as $type) {
            if (strtolower($type->id()) === $id) {
                return $type;
            }
        }

        return null;
    }

    public function require(string $id): ContentTypeInterface
    {
        return $this->get($id) ?? throw new RuntimeException(sprintf('Content type "%s" is not registered.', $id));
    }

    /**
     * @param class-string<EntityInterface> $modelClass
     */
    public function findByModel(string $modelClass): ?ContentTypeInterface
    {
        foreach ($this->types as $type) {
            if ($type->modelClass() === $modelClass) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param class-string<EntityInterface> $modelClass
     */
    public function requireByModel(string $modelClass): ContentTypeInterface
    {
        return $this->findByModel($modelClass)
            ?? throw new InvalidArgumentException(sprintf('No content type registered for "%s".', $modelClass));
    }

    /** @return array<string, ContentTypeInterface> */
    public function all(): array
    {
        return $this->types;
    }
}
