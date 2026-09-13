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
 * @template T of EntityInterface
 */
final class ContentApi
{
    public function __construct(
        private AbstractRepository $repository,
    ) {}

    /**
     * @return ?EntityInterface
     */
    public function find(int $id): ?EntityInterface
    {
        /** @var ?EntityInterface $entity */
        $entity = $this->repository->findOneBy('id', $id);
        return $entity;
    }

    /**
     * @param array<string, mixed>|string $criteria
     * @param array<string, string> $orderBy
     * @return EntityInterface
     */
    public function findBy(
        array|string $criteria,
        mixed $value = null,
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        /** @var EntityInterface $entities */
        $entities = $this->repository->findBy($criteria, $value, $orderBy, $limit, $offset);
        return $entities;
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
        /** @var ?EntityInterface $entity */
        $entity = $this->repository->findOneBy($criteria, $value, $orderBy);
        return $entity;
    }

    /**
     * @return EntityInterface
     */
    public function findAll(): array
    {
        /** @var EntityInterface $entities */
        $entities = $this->repository->findAll();
        return $entities;
    }
}
