<?php

declare(strict_types=1);

namespace Naf\CMS\Api;

use Naf\ORM\Core\EntityInterface;
use Naf\ORM\Repository\AbstractRepository;

/**
 * Generic content API. Exposes only operations that are meaningful for any
 * content type. Domain-specific behaviour (versioning, locales, publishing,
 * drafts) lives in the corresponding domain API (e.g. ArticleApi).
 *
 */
final class ContentApi
{
    public function __construct(
        private AbstractRepository $repository,
    ) {
    }

    /**
     * @return ?EntityInterface
     */
    public function find(int $id): ?EntityInterface
    {
        return $this->repository->findOneBy('id', $id);
    }

    /**
     * @param array<string, mixed>|string $criteria
     * @param array<string, string> $orderBy
     * @return list<EntityInterface>
     */
    public function findBy(
        array|string $criteria,
        mixed $value = null,
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        return $this->repository->findBy($criteria, $value, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed>|string $criteria
     * @param array<string, string> $orderBy
     * @return ?EntityInterface
     */
    public function findOneBy(
        array|string $criteria,
        mixed $value = null,
        array $orderBy = [],
    ): ?EntityInterface {
        return $this->repository->findOneBy($criteria, $value, $orderBy);
    }

    /**
     * @return list<EntityInterface>
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }
}
