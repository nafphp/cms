<?php

declare(strict_types=1);

namespace NixCMS\Stage;

final class StageTargetRegistry
{
    /** @var array<string, callable(array<string, mixed>): StageTargetInterface> */
    private array $resolvers = [];

    /**
     * @param callable(array<string, mixed>): StageTargetInterface $resolver
     */
    public function register(string $type, callable $resolver, bool $replace = false): self
    {
        $type = $this->normalizeType($type);
        if ($type === '') {
            throw new \InvalidArgumentException('Stage target type is required.');
        }

        if (isset($this->resolvers[$type]) && !$replace) {
            throw new \LogicException(sprintf('Stage target "%s" is already registered.', $type));
        }

        $this->resolvers[$type] = $resolver;
        return $this;
    }

    /**
     * @param array<string, mixed> $target
     */
    public function resolve(array $target): StageTargetInterface
    {
        $type = $this->normalizeType((string) ($target['type'] ?? ''));
        if ($type === '' || !isset($this->resolvers[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown stage target "%s".', $type));
        }

        return ($this->resolvers[$type])($target);
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys($this->resolvers);
    }

    private function normalizeType(string $type): string
    {
        return strtolower(trim($type));
    }
}
