<?php

declare(strict_types=1);

namespace NixCMS\Stage;

interface StageTargetInterface
{
    public function type(): string;

    public function id(): string;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array;

    /**
     * @param array<string, mixed> $snapshot
     */
    public function apply(StageChange $change, array $snapshot): StageTargetResult;

    public function commit(Stage $stage): StageCommitResult;
}
