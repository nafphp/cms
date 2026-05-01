<?php

declare(strict_types=1);

namespace Cms\Repositories;

use Cms\Models\Tag;
use Cms\Support\Slug;
use NixPHP\ORM\Repository\AbstractRepository;
use function NixPHP\ORM\em;

class TagRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return Tag::class;
    }

    /**
     * @param array<int, string> $names
     * @return array<int, Tag>
     */
    public function findOrCreateManyByNormalizedName(array $names): array
    {
        $tags = [];
        foreach ($this->normalizeNames($names) as $name) {
            $tags[] = $this->findOrCreateByNormalizedName($name);
        }

        return $tags;
    }

    public function findOrCreateByNormalizedName(string $name): Tag
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === '') {
            throw new \InvalidArgumentException('Tag name cannot be empty.');
        }

        $existing = $this->findOneByNormalizedName($normalizedName);
        if ($existing instanceof Tag) {
            return $existing;
        }

        $tag = new Tag([
            'name' => $normalizedName,
            'slug' => $this->buildUniqueSlug($normalizedName),
        ]);
        em()->save($tag);

        return $tag;
    }

    public function findOneByNormalizedName(string $name): ?Tag
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM tags WHERE LOWER(TRIM(name)) = LOWER(:name) ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['name' => $normalizedName]);
        $tagId = $stmt->fetchColumn();

        if ($tagId === false) {
            return null;
        }

        /** @var Tag|null $tag */
        $tag = $this->findOneBy('id', (int) $tagId);

        return $tag;
    }

    /**
     * @param array<int, string> $names
     * @return array<int, string>
     */
    private function normalizeNames(array $names): array
    {
        $normalized = [];
        $seen = [];
        foreach ($names as $name) {
            $value = $this->normalizeName($name);
            if ($value === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $value;
        }

        return $normalized;
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    private function buildUniqueSlug(string $name): string
    {
        $base = Slug::fromString($name);
        if ($base === '') {
            $base = 'tag';
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
        $stmt = $this->pdo->prepare('SELECT 1 FROM tags WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetchColumn() !== false;
    }
}
