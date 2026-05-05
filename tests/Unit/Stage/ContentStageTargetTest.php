<?php

declare(strict_types=1);

namespace Tests\Unit\Stage;

use NixCMS\Content\ContentTypeInterface;
use NixCMS\Repositories\StageRepository;
use NixCMS\Stage\Attributes\StageField;
use NixCMS\Stage\Stage;
use NixCMS\Stage\Targets\ContentStageTarget;
use Tests\Support\DatabaseTestCase;

final class ContentStageTargetTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(<<<'SQL'
            CREATE TABLE cms_stages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                target_type TEXT NOT NULL,
                target_id TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                base_snapshot_json TEXT NOT NULL,
                working_snapshot_json TEXT NOT NULL,
                changes_json TEXT NOT NULL,
                ui_actions_json TEXT NOT NULL,
                warnings_json TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NULL,
                committed_at TEXT NULL
            )
        SQL);
    }

    public function testDeclaredWritableFieldsAreStagedAndCommittedThroughSaveStage(): void
    {
        $api = new ContentStageTargetTestApi();
        \cms()->contentTypes()->add(new ContentStageTargetTestType('stage_test_article', $api), true);

        $target = new ContentStageTarget('stage_test_article', ContentStageTargetTestModel::class, [
            'id' => 42,
            'title' => 'Old title',
            'status' => 'draft',
            'identifier' => 'stable-id',
        ]);
        $stage = Stage::create($target, new StageRepository($this->pdo));

        $stage->change('content.field.set', [
            'field' => 'title',
            'value' => 'New title',
        ]);
        $result = $stage->commit();

        self::assertTrue($result->successful());
        self::assertSame('committed', $stage->status());
        self::assertSame('New title', $api->lastSnapshot['fields']['title'] ?? null);
        self::assertSame('stable-id', $api->lastSnapshot['fields']['identifier'] ?? null);
        self::assertSame(77, $result->toArray()['contentId'] ?? null);
    }

    public function testReadOnlyFieldsAreRejected(): void
    {
        \cms()->contentTypes()->add(new ContentStageTargetTestType('stage_test_readonly', new ContentStageTargetTestApi()), true);

        $target = new ContentStageTarget('stage_test_readonly', ContentStageTargetTestModel::class, [
            'id' => 42,
            'title' => 'Title',
            'status' => 'draft',
            'identifier' => 'stable-id',
        ]);
        $stage = Stage::create($target, new StageRepository($this->pdo));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "identifier" is not writable through stage.');

        $stage->change('content.field.set', [
            'field' => 'identifier',
            'value' => 'changed',
        ]);
    }

    public function testCommitReportsUnsupportedPersistenceWithoutRepositoryFallback(): void
    {
        \cms()->contentTypes()->add(new ContentStageTargetTestType('stage_test_unsupported', new \stdClass()), true);

        $target = new ContentStageTarget('stage_test_unsupported', ContentStageTargetTestModel::class, [
            'id' => 42,
            'title' => 'Title',
            'status' => 'draft',
            'identifier' => 'stable-id',
        ]);
        $stage = Stage::create($target, new StageRepository($this->pdo));

        $result = $stage->commit();
        $data = $result->toArray();

        self::assertFalse($result->successful());
        self::assertSame('open', $stage->status());
        self::assertFalse($data['persistSupported'] ?? true);
        self::assertStringContainsString('does not support staged persistence', (string) ($data['message'] ?? ''));
    }

    public function testCommitRejectsReadOnlyChangesFromReplacedSnapshot(): void
    {
        \cms()->contentTypes()->add(new ContentStageTargetTestType('stage_test_snapshot_guard', new ContentStageTargetTestApi()), true);

        $target = new ContentStageTarget('stage_test_snapshot_guard', ContentStageTargetTestModel::class, [
            'id' => 42,
            'title' => 'Title',
            'status' => 'draft',
            'identifier' => 'stable-id',
        ]);
        $stage = Stage::create($target, new StageRepository($this->pdo));
        $snapshot = $stage->workingSnapshot();
        $snapshot['fields']['identifier'] = 'changed';
        $stage->replaceWorkingSnapshot($snapshot);

        $result = $stage->commit();

        self::assertFalse($result->successful());
        self::assertSame('open', $stage->status());
        self::assertSame('Field "identifier" is not writable through stage.', $result->toArray()['message'] ?? null);
    }
}

final class ContentStageTargetTestModel
{
    #[StageField]
    protected ?string $title = null;
    #[StageField]
    protected ?string $status = null;
    #[StageField(writable: false)]
    protected ?string $identifier = null;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->title = isset($data['title']) ? (string) $data['title'] : null;
        $this->status = isset($data['status']) ? (string) $data['status'] : null;
        $this->identifier = isset($data['identifier']) ? (string) $data['identifier'] : null;
    }
}

final class ContentStageTargetTestApi
{
    /** @var array<string, mixed> */
    public array $lastSnapshot = [];

    /** @param array<string, mixed> $snapshot */
    public function saveStage(array $snapshot): array
    {
        $this->lastSnapshot = $snapshot;

        return ['contentId' => 77];
    }
}

final readonly class ContentStageTargetTestType implements ContentTypeInterface
{
    public function __construct(
        private string $id,
        private object $api,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return 'Stage Test';
    }

    public function modelClass(): string
    {
        return ContentStageTargetTestModel::class;
    }

    public function api(): object
    {
        return $this->api;
    }
}
