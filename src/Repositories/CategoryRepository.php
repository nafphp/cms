<?php

declare(strict_types=1);

namespace NixPHP\CMS\Repositories;

use NixPHP\CMS\Models\Category;
use NixPHP\CMS\Support\Slug;
use NixPHP\ORM\Repository\AbstractRepository;
use function NixPHP\ORM\em;

class CategoryRepository extends AbstractRepository
{
    protected function getTable(bool $singular = false): string
    {
        return 'categories';
    }

    protected function getEntityClass(): string
    {
        return Category::class;
    }

    /**
     * @return array<int, Category>
     */
    public function findAllOrderedByName(): array
    {
        /** @var array<int, Category> $categories */
        $categories = $this->findAll();

        usort(
            $categories,
            static fn(Category $a, Category $b): int => strcmp((string) $a->getName(), (string) $b->getName())
        );

        return $categories;
    }

    public function findOrCreateByNormalizedName(?string $name): ?Category
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === null) {
            return null;
        }

        $existing = $this->findOneByNormalizedName($normalizedName);
        if ($existing instanceof Category) {
            return $existing;
        }

        $category = new Category([
            'name' => $normalizedName,
            'slug' => $this->buildUniqueSlug($normalizedName),
        ]);
        em()->save($category);

        return $category;
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string,article_count:int}>
     */
    public function findAllWithArticleCount(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.id, c.name, c.slug, COUNT(a.id) AS article_count
             FROM categories c
             LEFT JOIN articles a ON a.category_id = c.id
             GROUP BY c.id, c.name, c.slug
             ORDER BY c.name ASC'
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'article_count' => (int) ($row['article_count'] ?? 0),
            ],
            $rows
        );
    }

    public function renameByIdNormalized(int $id, ?string $name): bool
    {
        $normalizedName = $this->normalizeName($name);
        if ($id <= 0 || $normalizedName === null) {
            return false;
        }

        $existing = $this->findOneByNormalizedName($normalizedName);
        if ($existing instanceof Category && $existing->getId() !== $id) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
        $stmt->execute([
            'name' => $normalizedName,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByIdWithArticleDetach(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $detachStmt = $this->pdo->prepare('UPDATE articles SET category_id = NULL WHERE category_id = :id');
            $detachStmt->execute(['id' => $id]);

            $deleteStmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
            $deleteStmt->execute(['id' => $id]);

            $this->pdo->commit();

            return $deleteStmt->rowCount() > 0;
        } catch (\Throwable) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function findOneByNormalizedName(string $name): ?Category
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === null) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM categories WHERE LOWER(TRIM(name)) = LOWER(:name) ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['name' => $normalizedName]);
        $categoryId = $stmt->fetchColumn();

        if ($categoryId === false) {
            return null;
        }

        /** @var Category|null $category */
        $category = $this->findOneBy('id', (int) $categoryId);

        return $category;
    }

    public function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($normalized === '' || strtolower($normalized) === 'none') {
            return null;
        }

        return $normalized;
    }

    private function buildUniqueSlug(string $name): string
    {
        $base = Slug::fromString($name);
        if ($base === '') {
            $base = 'category';
        }

        $candidate = $base;
        $suffix = 1;
        while ($this->slugExists($candidate)) {
            $suffix++;
            $candidate = $base . '-' . $suffix;
        }

        return $candidate;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetchColumn() !== false;
    }
}
