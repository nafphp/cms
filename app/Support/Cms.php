<?php

declare(strict_types=1);

namespace NixCMS\Support;

use NixCMS\Api\ArticleApi;
use NixCMS\Api\ApiRegistry;
use NixCMS\Api\FormApi;
use NixCMS\Api\MediaApi;
use NixCMS\Api\NavigationApi;
use NixCMS\Content\BlockViewRegistry;
use NixCMS\Content\ContentRegistry;
use NixCMS\Content\Page\Block\BlockTypeRegistry;
use NixCMS\Localization\LanguageRegistry;
use NixCMS\Models\Article;
use NixCMS\Models\Form;
use NixCMS\Models\Media;
use NixCMS\Models\SiteNavigation;
use NixCMS\Navigation\NavigationRegistry;
use NixCMS\Page\PageRegistry;
use NixCMS\Repositories\ThemeRepository;
use NixCMS\Settings\SettingsRegistry;
use NixCMS\Stage\StageTargetRegistry;
use NixCMS\Stage\Targets\ContentStageTarget;
use NixCMS\Stage\Targets\PageStageTarget;
use NixCMS\Stage\Targets\ThemeStageTarget;
use NixCMS\Storage\EntityStorage;
use NixCMS\Theme\ThemeRegistry;
use NixCMS\Visualization\VisualizationRegistry;

final class Cms
{
    private ?ApiRegistry $apiRegistry = null;
    private ?Settings $settings = null;
    private ?ContentRegistry $contentRegistry = null;
    private ?PageRegistry $pageRegistry = null;
    private ?NavigationRegistry $navigationRegistry = null;
    private ?SettingsRegistry $settingsRegistry = null;
    private ?VisualizationRegistry $visualizationRegistry = null;
    private ?LanguageRegistry $languageRegistry = null;
    private ?BlockViewRegistry $blockViewRegistry = null;
    private ?BlockTypeRegistry $blockTypeRegistry = null;
    private ?ThemeRegistry $themeRegistry = null;
    private ?StageTargetRegistry $stageTargetRegistry = null;

    public function settings(): Settings
    {
        return $this->settings ??= new Settings();
    }

    public function languages(): LanguageRegistry
    {
        return $this->languageRegistry ??= new LanguageRegistry();
    }

    public function settingsRegistry(): SettingsRegistry
    {
        if ($this->settingsRegistry === null) {
            $this->settingsRegistry = new SettingsRegistry();
            $settingsFile = BASE_PATH . '/app/settings.php';
            if (is_file($settingsFile)) {
                $this->settingsRegistry->addMany(require $settingsFile);
            }
        }

        return $this->settingsRegistry;
    }

    public function pages(): PageRegistry
    {
        return $this->pageRegistry ??= new PageRegistry();
    }

    public function navigationRegistry(): NavigationRegistry
    {
        if ($this->navigationRegistry === null) {
            $this->navigationRegistry = new NavigationRegistry();
            $navigationFile = BASE_PATH . '/app/navigation.php';
            if (is_file($navigationFile)) {
                $this->navigationRegistry->addMany(require $navigationFile);
            }
        }

        return $this->navigationRegistry;
    }

    public function navigation(): NavigationApi
    {
        /** @var NavigationApi $api */
        $api = $this->content(SiteNavigation::class);
        return $api;
    }

    public function contentTypes(): ContentRegistry
    {
        if ($this->contentRegistry === null) {
            $this->contentRegistry = new ContentRegistry();
            $contentFile = BASE_PATH . '/app/content.php';
            if (is_file($contentFile)) {
                $this->contentRegistry->addMany(require $contentFile);
            }
        }

        return $this->contentRegistry;
    }

    public function api(): ApiRegistry
    {
        return $this->apiRegistry ??= new ApiRegistry();
    }

    public function visualizations(): VisualizationRegistry
    {
        return $this->visualizationRegistry ??= new VisualizationRegistry();
    }

    public function blocks(): BlockViewRegistry
    {
        return $this->blockViewRegistry ??= new BlockViewRegistry();
    }

    public function blockTypes(): BlockTypeRegistry
    {
        return $this->blockTypeRegistry ??= new BlockTypeRegistry();
    }

    public function themes(): ThemeRegistry
    {
        if ($this->themeRegistry === null) {
            $this->themeRegistry = new ThemeRegistry();
            $themeFile = BASE_PATH . '/app/themes.php';
            if (is_file($themeFile)) {
                $this->themeRegistry->addMany(require $themeFile);
            }
            $this->themeRegistry->addMany((new ThemeRepository())->themes(), true);
        }

        return $this->themeRegistry;
    }

    public function stageTargets(): StageTargetRegistry
    {
        if ($this->stageTargetRegistry === null) {
            $this->stageTargetRegistry = new StageTargetRegistry();
            $this->stageTargetRegistry->register('page', function (array $target): PageStageTarget {
                if (is_array($target['snapshot'] ?? null)) {
                    return new PageStageTarget($target['snapshot']);
                }

                $id = (int) ($target['id'] ?? $target['pageId'] ?? 0);
                $page = (new \NixCMS\Repositories\PageRepository())->find($id);
                if (!is_array($page)) {
                    throw new \InvalidArgumentException(sprintf('Page "%d" does not exist.', $id));
                }

                return new PageStageTarget($page);
            });
            $this->stageTargetRegistry->register('theme', function (array $target): ThemeStageTarget {
                if (is_array($target['snapshot'] ?? null)) {
                    return new ThemeStageTarget($target['snapshot']);
                }

                $id = $target['id'] ?? $target['identifier'] ?? '';
                $theme = (new ThemeRepository())->find(is_numeric($id) ? (int) $id : (string) $id);
                if (!is_array($theme)) {
                    throw new \InvalidArgumentException(sprintf('Theme "%s" does not exist.', (string) $id));
                }

                return new ThemeStageTarget($theme);
            });
            $this->stageTargetRegistry->register('content', function (array $target): ContentStageTarget {
                $contentTypeId = trim((string) ($target['contentType'] ?? $target['content_type'] ?? ''));
                $contentType = $this->contentTypes()->get($contentTypeId);
                if ($contentType === null) {
                    throw new \InvalidArgumentException(sprintf('Content type "%s" does not exist.', $contentTypeId));
                }

                $snapshot = is_array($target['snapshot'] ?? null) ? $target['snapshot'] : null;
                $id = (int) ($target['id'] ?? $target['contentId'] ?? $snapshot['id'] ?? 0);
                if ($snapshot !== null) {
                    $row = [
                        'id' => $id,
                        ...$this->contentStageRowFromSnapshot($snapshot),
                    ];

                    return new ContentStageTarget($contentType->id(), $contentType->modelClass(), $row);
                }

                $repositoryClass = 'NixCMS\\Repositories\\' . substr($contentType->modelClass(), strrpos($contentType->modelClass(), '\\') + 1) . 'Repository';
                if (!class_exists($repositoryClass)) {
                    throw new \RuntimeException(sprintf('No repository found for content type "%s".', $contentTypeId));
                }

                $repository = new $repositoryClass();
                if (!method_exists($repository, 'find')) {
                    throw new \RuntimeException(sprintf('Content repository for "%s" cannot load staged content.', $contentTypeId));
                }

                $row = $repository->find($id);
                if (!is_array($row)) {
                    throw new \InvalidArgumentException(sprintf('Content item "%s:%d" does not exist.', $contentTypeId, $id));
                }

                return new ContentStageTarget($contentType->id(), $contentType->modelClass(), $row);
            });
        }

        return $this->stageTargetRegistry;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function contentStageRowFromSnapshot(array $snapshot): array
    {
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : $snapshot;
        $row = $fields;

        if (isset($fields['fields']) && is_array($fields['fields'])) {
            $row['fields_json'] = json_encode($fields['fields'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (isset($fields['settings']) && is_array($fields['settings'])) {
            $row['settings_json'] = json_encode($fields['settings'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (isset($fields['items']) && is_array($fields['items'])) {
            $row['items_json'] = json_encode($fields['items'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (isset($fields['metadata']) && is_array($fields['metadata'])) {
            $row['metadata_json'] = json_encode($fields['metadata'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $row;
    }

    public function articles(): ArticleApi
    {
        /** @var ArticleApi $api */
        $api = $this->content(Article::class);
        return $api;
    }

    public function forms(): FormApi
    {
        /** @var FormApi $api */
        $api = $this->content(Form::class);
        return $api;
    }

    public function media(): MediaApi
    {
        /** @var MediaApi $api */
        $api = $this->content(Media::class);
        return $api;
    }

    /**
     * @template T of \NixPHP\ORM\Core\EntityInterface
     * @param class-string<T> $class
     * @return EntityStorage<T>
     */
    public function storage(string $class): EntityStorage
    {
        return new EntityStorage($class);
    }

    /**
     * @param class-string $class
     */
    public function content(string $class): object
    {
        $type = $this->contentTypes()->findByModel($class);
        if ($type !== null) {
            return $type->api();
        }

        throw new \InvalidArgumentException(sprintf(
            'No content type registered for "%s". Register one through cms()->contentTypes()->add(...).',
            $class
        ));
    }
}
