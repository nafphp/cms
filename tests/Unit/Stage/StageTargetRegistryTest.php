<?php

declare(strict_types=1);

namespace Tests\Unit\Stage;

use NixCMS\Stage\StageCommitResult;
use NixCMS\Stage\Stage;
use NixCMS\Stage\StageChange;
use NixCMS\Stage\StageTargetInterface;
use NixCMS\Stage\StageTargetRegistry;
use NixCMS\Stage\StageTargetResult;
use PHPUnit\Framework\TestCase;

final class StageTargetRegistryTest extends TestCase
{
    public function testRegistryResolvesStageTargetsByType(): void
    {
        $registry = new StageTargetRegistry();
        $registry->register('fixture', fn(array $target): StageTargetInterface => new FixtureStageTarget((string) $target['id']));

        $target = $registry->resolve(['type' => 'fixture', 'id' => 'abc']);

        self::assertSame('fixture', $target->type());
        self::assertSame('abc', $target->id());
    }
}

final class FixtureStageTarget implements StageTargetInterface
{
    public function __construct(private string $id) {}

    public function type(): string
    {
        return 'fixture';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function snapshot(): array
    {
        return ['id' => $this->id];
    }

    public function apply(StageChange $change, array $snapshot): StageTargetResult
    {
        return StageTargetResult::create($snapshot);
    }

    public function commit(Stage $stage): StageCommitResult
    {
        return StageCommitResult::success();
    }
}
