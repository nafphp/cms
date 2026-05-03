<?php

declare(strict_types=1);

namespace NixCMS\Api;

use NixCMS\Navigation\Navigation;
use NixCMS\Repositories\NavigationRepository;
use function NixPHP\I18n\t;

final class NavigationApi
{
    public function __construct(private NavigationRepository $repository)
    {
    }

    public function get(string $identifier, ?string $language = null, string $scope = 'frontend'): ?Navigation
    {
        $stored = $this->repository->findByIdentifier($identifier, $scope, $language);
        if ($stored instanceof Navigation) {
            return $stored;
        }

        return \cms()->navigationRegistry()->getNavigation($identifier, $scope, $language);
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
        return $this->repository->save($payload);
    }

    public function publish(int $id): bool
    {
        return $this->repository->publish($id);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
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
}
