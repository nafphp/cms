<?php

declare(strict_types=1);

namespace NixCMS\Stage;

final readonly class StageCommitResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private bool $success,
        private string $message = '',
        private array $data = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function success(string $message = 'Stage committed.', array $data = []): self
    {
        return new self(true, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function failure(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }

    public function successful(): bool
    {
        return $this->success;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            ...$this->data,
        ];
    }
}
