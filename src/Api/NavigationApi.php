<?php

declare(strict_types=1);

namespace Naf\CMS\Api;

use Naf\CMS\Contracts\ScopeAwareInterface;
use Naf\CMS\Enums\Scope;
use Naf\CMS\Events\ContentEvent;
use Naf\CMS\Models\SiteNavigation;
use Naf\CMS\Navigation\Navigation;
use Naf\CMS\Navigation\NavigationItem;
use Naf\CMS\Navigation\NavigationRegistry;
use Naf\CMS\Repositories\NavigationRepository;
use Naf\CMS\Storage\EntityStorage;
use Naf\CMS\Support\AuditEntry;

use function Naf\I18n\t;

/**
 * Domain API for site navigations. Exposes navigation-specific behaviour and delegates persistence/queries to NavigationRepository.
 *
 * Data access path: ContentApi → Storage<SiteNavigation> → NavigationRepository → db()
 * Use ->storage() for generic Criteria-based reads, ->repository() for tree-aware queries.
 */
final class NavigationApi implements ScopeAwareInterface
{
    /**
     * @var EntityStorage<SiteNavigation>|null
     */
    private ?EntityStorage $storage       = null;
    private ?NavigationRegistry $registry = null;

    public function __construct(
        private NavigationRepository $repository,
        bool $loadConfiguredRegistry = true,
    ) {
        // Navigation definitions are registered explicitly at bootstrap. Retain the old argument for callers.
        unset($loadConfiguredRegistry);
    }

    /**
     * @return EntityStorage<SiteNavigation>
     */
    public function storage(): EntityStorage
    {
        return $this->storage ??= new EntityStorage(SiteNavigation::class);
    }

    public function repository(): NavigationRepository
    {
        return $this->repository;
    }

    public function forScope(Scope $scope): ScopedNavigationApi
    {
        return new ScopedNavigationApi($this, $scope);
    }

    public function get(string $identifier, ?string $language = null, string $scope = 'frontend'): ?Navigation
    {
        $stored = $this->repository->findByIdentifier($identifier, $scope, $language);
        if ($stored instanceof Navigation) {
            return $stored;
        }

        return $this->registry()->getNavigation($identifier, $scope, $language);
    }

    private function registry(): NavigationRegistry
    {
        if ($this->registry === null) {
            $this->registry = new NavigationRegistry();
        }

        return $this->registry;
    }

    /**
     * @param NavigationItem|array<string, mixed> $item
     */
    public function add(NavigationItem|array $item, bool $replace = false): self
    {
        $this->registry()->add($item, $replace);

        return $this;
    }

    /**
     * @param iterable<NavigationItem|Navigation|array<string, mixed>> $items
     */
    public function addMany(iterable $items, bool $replace = false): self
    {
        $this->registry()->addMany($items, $replace);

        return $this;
    }

    /**
     * @param Navigation|array<array-key, mixed> $navigation
     */
    public function addNavigation(Navigation|array $navigation, bool $replace = false): self
    {
        $this->registry()->addNavigation($navigation, $replace);

        return $this;
    }

    public function item(string $key): ?NavigationItem
    {
        return $this->registry()->get($key);
    }

    /**
     * @return array<string, NavigationItem>
     */
    public function allForScope(string $scope): array
    {
        return $this->registry()->allForScope($scope);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(string $identifier, ?string $language = null, string $scope = 'frontend'): array
    {
        $navigation = $this->get($identifier, $language, $scope);
        if (!$navigation instanceof Navigation) {
            return [];
        }

        return $this->translateItems($navigation->toArray()['items']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summaries(string $scope = 'frontend'): array
    {
        return $this->repository->summaries($scope);
    }

    public function find(int $id): ?Navigation
    {
        return $this->repository->find($id);
    }

    /**
     * @return array<string, Navigation>
     */
    public function translations(string $identifier, string $scope = 'frontend'): array
    {
        return $this->repository->findTranslations($identifier, $scope);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): int
    {
        $id      = (int) ($payload['id'] ?? 0);
        $old     = $id > 0 ? $this->repository->find($id) : null;
        $savedId = $this->repository->save($payload);

        AuditEntry::create(
            $old instanceof Navigation ? 'navigation.updated' : 'navigation.created',
            'navigation',
            (string) $savedId,
            $this->navigationAuditSnapshot($old),
            $this->navigationAuditSnapshot($this->repository->find($savedId)),
        )->record();
        ContentEvent::dispatch(
            $old instanceof Navigation ? 'navigation.updated' : 'navigation.created',
            'navigation',
            (string) $savedId,
            $this->navigationAuditSnapshot($old),
            $this->navigationAuditSnapshot($this->repository->find($savedId)),
        );

        return $savedId;
    }

    public function publish(int $id): bool
    {
        $old       = $this->repository->find($id);
        $published = $this->repository->publish($id);
        if ($published) {
            AuditEntry::create(
                'navigation.published',
                'navigation',
                (string) $id,
                $this->navigationAuditSnapshot($old),
                $this->navigationAuditSnapshot($this->repository->find($id)),
            )->record();
            ContentEvent::dispatch(
                'navigation.published',
                'navigation',
                (string) $id,
                $this->navigationAuditSnapshot($old),
                $this->navigationAuditSnapshot($this->repository->find($id)),
            );
        }

        return $published;
    }

    public function delete(int $id): bool
    {
        $old     = $this->repository->find($id);
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            AuditEntry::create(
                'navigation.deleted',
                'navigation',
                (string) $id,
                $this->navigationAuditSnapshot($old),
            )->record();
            ContentEvent::dispatch(
                'navigation.deleted',
                'navigation',
                (string) $id,
                $this->navigationAuditSnapshot($old),
            );
        }

        return $deleted;
    }

    public function deleteTranslationsExcept(string $identifier, string $scope, int $keepId): int
    {
        return $this->repository->deleteTranslationsExcept($identifier, $scope, $keepId);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function translateItems(array $items): array
    {
        foreach ($items as &$item) {
            foreach (['label', 'title'] as $field) {
                if (isset($item[$field]) && is_string($item[$field]) && trim($item[$field]) !== '') {
                    $item[$field] = t($item[$field]);
                }
            }

            if (is_array($item['children'] ?? null)) {
                $item['children'] = $this->translateItems($item['children']);
            }
        }
        unset($item);

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function navigationAuditSnapshot(?Navigation $navigation): ?array
    {
        if (!$navigation instanceof Navigation) {
            return null;
        }

        $data = $navigation->toArray();

        return [
            'id'         => $navigation->id(),
            'identifier' => $navigation->identifier(),
            'title'      => $navigation->title(),
            'scope'      => $navigation->scope(),
            'language'   => $navigation->language(),
            'status'     => $navigation->status(),
            'items'      => $data['items'] ?? [],
        ];
    }
}
