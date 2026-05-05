<?php

declare(strict_types=1);

namespace NixCMS\Stage;

final readonly class StageTargetResult
{
    /**
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $uiActions
     * @param array<int, string> $warnings
     */
    public function __construct(
        private array $snapshot,
        private array $uiActions = [],
        private array $warnings = [],
    ) {}

    /**
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $uiActions
     * @param array<int, string> $warnings
     */
    public static function create(array $snapshot, array $uiActions = [], array $warnings = []): self
    {
        return new self($snapshot, $uiActions, $warnings);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->snapshot;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function uiActions(): array
    {
        return $this->uiActions;
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
