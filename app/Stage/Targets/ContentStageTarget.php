<?php

declare(strict_types=1);

namespace NixCMS\Stage\Targets;

use NixCMS\Stage\Stage;
use NixCMS\Stage\StageChange;
use NixCMS\Stage\StageCommitResult;
use NixCMS\Stage\StageReflection;
use NixCMS\Stage\StageTargetInterface;
use NixCMS\Stage\StageTargetResult;

final class ContentStageTarget implements StageTargetInterface
{
    /**
     * @param class-string $modelClass
     * @param array<string, mixed> $row
     */
    public function __construct(
        private string $contentType,
        private string $modelClass,
        private array $row,
        private ?StageReflection $reflection = null,
    ) {}

    public function type(): string
    {
        return 'content';
    }

    public function id(): string
    {
        return $this->contentType . ':' . (string) (int) ($this->row['id'] ?? 0);
    }

    public function snapshot(): array
    {
        $model = new $this->modelClass($this->row);

        return [
            'id' => (int) ($this->row['id'] ?? 0),
            'contentType' => $this->contentType,
            'modelClass' => $this->modelClass,
            'fields' => $this->reflection()->snapshot($model),
        ];
    }

    public function apply(StageChange $change, array $snapshot): StageTargetResult
    {
        return match ($change->type()) {
            'content.field.set' => $this->setField($snapshot, $change->payload()),
            'content.field.merge' => $this->mergeField($snapshot, $change->payload()),
            default => throw new \InvalidArgumentException(sprintf('Unsupported content stage change "%s".', $change->type())),
        };
    }

    public function commit(Stage $stage): StageCommitResult
    {
        $snapshot = $stage->workingSnapshot();
        $baseSnapshot = $stage->baseSnapshot();
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $payload = ['id' => (int) ($snapshot['id'] ?? 0)];
        $definitions = $this->reflection()->fields($this->modelClass);

        foreach ($definitions as $fieldName => $definition) {
            if (array_key_exists($fieldName, $fields)) {
                $payload[$fieldName] = $fields[$fieldName];
            }
        }

        $readOnlyError = $this->readOnlyMutationError($baseSnapshot, $fields, $definitions);
        if ($readOnlyError !== null) {
            return StageCommitResult::failure($readOnlyError, [
                'contentType' => $this->contentType,
                'persistSupported' => true,
            ]);
        }

        $api = \cms()->contentTypes()->get($this->contentType)?->api();
        if (!is_object($api)) {
            return StageCommitResult::failure(sprintf('Content type "%s" has no API for staged persistence.', $this->contentType), [
                'contentType' => $this->contentType,
                'persistSupported' => false,
            ]);
        }

        if (method_exists($api, 'saveStage')) {
            $result = $api->saveStage([
                ...$snapshot,
                'id' => (int) ($snapshot['id'] ?? 0),
                'fields' => $fields,
                'payload' => $payload,
            ]);

            return $this->commitResult($result, $fields);
        }

        if (method_exists($api, 'save')) {
            $result = $api->save($payload);

            return $this->commitResult($result, $fields);
        }

        return StageCommitResult::failure(sprintf('Content type "%s" does not support staged persistence.', $this->contentType), [
            'contentType' => $this->contentType,
            'persistSupported' => false,
            'publishRequired' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $payload
     */
    private function setField(array $snapshot, array $payload): StageTargetResult
    {
        $field = trim((string) ($payload['field'] ?? ''));
        $this->assertWritable($field);

        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $fields[$field] = $payload['value'] ?? null;
        $snapshot['fields'] = $fields;

        return StageTargetResult::create($snapshot);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $payload
     */
    private function mergeField(array $snapshot, array $payload): StageTargetResult
    {
        $field = trim((string) ($payload['field'] ?? ''));
        $this->assertWritable($field);

        $value = $payload['value'] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException('content.field.merge requires an object or array value.');
        }

        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $current = is_array($fields[$field] ?? null) ? $fields[$field] : [];
        $fields[$field] = array_replace_recursive($current, $value);
        $snapshot['fields'] = $fields;

        return StageTargetResult::create($snapshot);
    }

    private function assertWritable(string $field): void
    {
        $definition = $this->reflection()->fields($this->modelClass)[$field] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException(sprintf('Field "%s" is not stageable.', $field));
        }
        if (!$definition->writable) {
            throw new \InvalidArgumentException(sprintf('Field "%s" is not writable through stage.', $field));
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function publishRequired(array $fields): bool
    {
        return isset($fields['status']) && (string) $fields['status'] !== (string) ($this->row['status'] ?? '');
    }

    /**
     * @param mixed $result
     * @param array<string, mixed> $fields
     */
    private function commitResult(mixed $result, array $fields): StageCommitResult
    {
        $data = is_array($result) ? $result : ['contentId' => (int) $result];
        $contentId = (int) ($data['contentId'] ?? $data['id'] ?? $data['articleId'] ?? $data['formId'] ?? $data['navigationId'] ?? 0);

        return StageCommitResult::success('Content stage committed.', [
            'contentType' => $this->contentType,
            'contentId' => $contentId,
            'persistSupported' => true,
            'publishRequired' => $this->publishRequired($fields),
            ...$data,
        ]);
    }

    /**
     * @param array<string, mixed> $baseSnapshot
     * @param array<string, mixed> $fields
     * @param array<string, \NixCMS\Stage\StageFieldDefinition> $definitions
     */
    private function readOnlyMutationError(array $baseSnapshot, array $fields, array $definitions): ?string
    {
        $baseFields = is_array($baseSnapshot['fields'] ?? null) ? $baseSnapshot['fields'] : [];
        foreach ($definitions as $fieldName => $definition) {
            if ($definition->writable || !array_key_exists($fieldName, $fields)) {
                continue;
            }

            if (($fields[$fieldName] ?? null) !== ($baseFields[$fieldName] ?? null)) {
                return sprintf('Field "%s" is not writable through stage.', $fieldName);
            }
        }

        return null;
    }

    private function reflection(): StageReflection
    {
        return $this->reflection ??= new StageReflection();
    }
}
