<?php

declare(strict_types=1);

namespace NixCMS\Stage;

use NixCMS\Repositories\StageRepository;

final class Stage
{
    /**
     * @param array<string, mixed> $baseSnapshot
     * @param array<string, mixed> $workingSnapshot
     * @param array<int, StageChange> $changes
     * @param array<int, array<string, mixed>> $uiActions
     * @param array<int, string> $warnings
     */
    private function __construct(
        private StageTargetInterface $target,
        private ?int $id,
        private array $baseSnapshot,
        private array $workingSnapshot,
        private array $changes = [],
        private array $uiActions = [],
        private array $warnings = [],
        private string $status = 'open',
        private ?StageRepository $repository = null,
    ) {}

    public static function create(StageTargetInterface $target, ?StageRepository $repository = null): self
    {
        $snapshot = $target->snapshot();

        return new self($target, null, $snapshot, $snapshot, repository: $repository);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(StageTargetInterface $target, array $row, ?StageRepository $repository = null): self
    {
        $changes = array_map(
            static fn(array $change): StageChange => StageChange::fromArray($change),
            is_array($row['changes'] ?? null) ? $row['changes'] : []
        );

        return new self(
            $target,
            (int) ($row['id'] ?? 0),
            is_array($row['base_snapshot'] ?? null) ? $row['base_snapshot'] : [],
            is_array($row['working_snapshot'] ?? null) ? $row['working_snapshot'] : [],
            $changes,
            is_array($row['ui_actions'] ?? null) ? $row['ui_actions'] : [],
            is_array($row['warnings'] ?? null) ? $row['warnings'] : [],
            (string) ($row['status'] ?? 'open'),
            $repository,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function change(string $type, array $payload = []): self
    {
        $this->assertOpen();

        $change = StageChange::create($type, $payload);
        $result = $this->target->apply($change, $this->workingSnapshot);

        $this->changes[] = $change;
        $this->workingSnapshot = $result->snapshot();
        $this->uiActions = [...$this->uiActions, ...$result->uiActions()];
        $this->warnings = [...$this->warnings, ...$result->warnings()];
        $this->persist();

        return $this;
    }

    public function commit(): StageCommitResult
    {
        $this->assertOpen();

        $result = $this->target->commit($this);
        if ($result->successful()) {
            $this->status = 'committed';
            $this->persist();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function replaceWorkingSnapshot(array $snapshot, bool $persist = true): self
    {
        $this->assertOpen();
        $this->workingSnapshot = $snapshot;

        if ($persist) {
            $this->persist();
        }

        return $this;
    }

    public function persist(): self
    {
        if ($this->repository === null) {
            $this->repository = new StageRepository();
        }

        $this->id = $this->repository->save($this);

        return $this;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function target(): StageTargetInterface
    {
        return $this->target;
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function baseSnapshot(): array
    {
        return $this->baseSnapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function workingSnapshot(): array
    {
        return $this->workingSnapshot;
    }

    /**
     * @return array<int, StageChange>
     */
    public function changes(): array
    {
        return $this->changes;
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

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        return [
            'stageId' => $this->id,
            'target' => [
                'type' => $this->target->type(),
                'id' => $this->target->id(),
            ],
            'status' => $this->status,
            'baseSnapshot' => $this->baseSnapshot,
            'workingSnapshot' => $this->workingSnapshot,
            'changes' => array_map(static fn(StageChange $change): array => $change->toArray(), $this->changes),
            'uiActions' => $this->uiActions,
            'warnings' => $this->warnings,
        ];
    }

    private function assertOpen(): void
    {
        if ($this->status !== 'open') {
            throw new \RuntimeException(sprintf('Stage is "%s" and cannot be changed.', $this->status));
        }
    }
}
