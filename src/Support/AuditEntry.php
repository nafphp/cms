<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use function Naf\CMS\audit;

final class AuditEntry
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $action,
        public readonly string $subjectType,
        public readonly string $subjectKey,
        public readonly mixed $oldValue = null,
        public readonly mixed $newValue = null,
        public readonly array $meta = [],
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function create(
        string $action,
        string $subjectType,
        string $subjectKey,
        mixed $oldValue = null,
        mixed $newValue = null,
        array $meta = [],
    ): self {
        return new self($action, $subjectType, $subjectKey, $oldValue, $newValue, $meta);
    }

    public function record(?Audit $audit = null): bool
    {
        return ($audit ?? audit())->record($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action'       => $this->action,
            'subject_type' => $this->subjectType,
            'subject_key'  => $this->subjectKey,
            'old_value'    => $this->oldValue,
            'new_value'    => $this->newValue,
            'meta'         => $this->meta,
        ];
    }
}
