<?php

declare(strict_types=1);

namespace Naf\CMS\Api;

use Naf\CMS\Models\Form;
use Naf\CMS\Repositories\FormRepository;
use Naf\CMS\Storage\EntityStorage;
use Naf\CMS\Support\AuditEntry;

/**
 * Domain API for forms. Exposes form-specific behaviour and delegates persistence/queries to FormRepository.
 *
 * Data access path: ContentApi → Storage<Form> → FormRepository → db()
 * Use ->storage() for generic Criteria-based reads, ->repository() for submission-aware queries.
 */
final class FormApi
{
    /**
     * @var EntityStorage<Form>|null
     */
    private ?EntityStorage $storage = null;

    public function __construct(private FormRepository $repository)
    {
    }

    /**
     * @return EntityStorage<Form>
     */
    public function storage(): EntityStorage
    {
        return $this->storage ??= new EntityStorage(Form::class);
    }

    public function repository(): FormRepository
    {
        return $this->repository;
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
        $id      = (int) ($payload['id'] ?? 0);
        $old     = $id > 0 ? $this->repository->find($id) : null;
        $savedId = $this->repository->save($payload);

        AuditEntry::create(
            $old === null ? 'form.created' : 'form.updated',
            'form',
            (string) $savedId,
            $this->formAuditSnapshot($old),
            $this->formAuditSnapshot($this->repository->find($savedId)),
        )->record();

        return $savedId;
    }

    public function delete(int $id): bool
    {
        $old     = $this->repository->find($id);
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            AuditEntry::create(
                'form.deleted',
                'form',
                (string) $id,
                $this->formAuditSnapshot($old),
            )->record();
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    public function storeSubmission(int $formId, array $payload, array $meta = []): int
    {
        return $this->repository->storeSubmission($formId, $payload, $meta);
    }

    /** @return array<int, array<string, mixed>> */
    public function submissions(int $formId, int $limit = 50): array
    {
        return $this->repository->submissions($formId, $limit);
    }

    /**
     * @param array<string, mixed>|null $form
     * @return array<string, mixed>|null
     */
    private function formAuditSnapshot(?array $form): ?array
    {
        if ($form === null) {
            return null;
        }

        return [
            'id'         => (int) ($form['id'] ?? 0),
            'identifier' => (string) ($form['identifier'] ?? ''),
            'title'      => (string) ($form['title'] ?? ''),
            'status'     => (string) ($form['status'] ?? ''),
            'fields'     => $form['fields'] ?? [],
            'settings'   => $form['settings'] ?? [],
        ];
    }
}
