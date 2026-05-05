<?php

declare(strict_types=1);

namespace NixCMS\Stage;

final readonly class StageChange
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private string $type,
        private array $payload,
        private string $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(string $type, array $payload = []): self
    {
        $type = trim($type);
        if ($type === '') {
            throw new \InvalidArgumentException('Stage change type is required.');
        }

        return new self($type, $payload, (new \DateTimeImmutable())->format(DATE_ATOM));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string) ($data['type'] ?? '')),
            is_array($data['payload'] ?? null) ? $data['payload'] : [],
            trim((string) ($data['createdAt'] ?? $data['created_at'] ?? '')) ?: (new \DateTimeImmutable())->format(DATE_ATOM),
        );
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'payload' => $this->payload,
            'createdAt' => $this->createdAt,
        ];
    }
}
