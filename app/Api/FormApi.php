<?php

declare(strict_types=1);

namespace NixCMS\Api;

use NixCMS\Repositories\FormRepository;

final class FormApi
{
    public function __construct(private FormRepository $repository)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function summaries(): array
    {
        return $this->repository->summaries();
    }

    /** @return array<int, array{label:string,value:string}> */
    public function choices(bool $publishedOnly = true): array
    {
        return $this->repository->choices($publishedOnly);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /** @return array<string, mixed>|null */
    public function findByIdentifier(string $identifier, bool $publishedOnly = true): ?array
    {
        return $this->repository->findByIdentifier($identifier, $publishedOnly);
    }

    /** @param array<string, mixed> $payload */
    public function save(array $payload): int
    {
        return $this->repository->save($payload);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /** @param array<string, mixed> $payload */
    public function storeSubmission(int $formId, array $payload, array $meta = []): int
    {
        return $this->repository->storeSubmission($formId, $payload, $meta);
    }

    /** @return array<int, array<string, mixed>> */
    public function submissions(int $formId, int $limit = 50): array
    {
        return $this->repository->submissions($formId, $limit);
    }
}
