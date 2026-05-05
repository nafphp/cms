<?php

declare(strict_types=1);

namespace NixCMS\Stage\Targets;

use NixCMS\Repositories\ThemeRepository;
use NixCMS\Services\Theme\ThemeAssetWriter;
use NixCMS\Stage\Stage;
use NixCMS\Stage\StageChange;
use NixCMS\Stage\StageCommitResult;
use NixCMS\Stage\StageTargetInterface;
use NixCMS\Stage\StageTargetResult;
use NixCMS\Theme\Theme;

final class ThemeStageTarget implements StageTargetInterface
{
    /** @param array<string, mixed> $theme */
    public function __construct(
        private array $theme,
        private ?ThemeRepository $themes = null,
        private ?ThemeAssetWriter $assets = null,
    ) {}

    public function type(): string
    {
        return 'theme';
    }

    public function id(): string
    {
        return (string) ($this->theme['id'] ?? $this->theme['identifier'] ?? '');
    }

    public function snapshot(): array
    {
        return [
            'id' => (int) ($this->theme['id'] ?? 0),
            'label' => (string) ($this->theme['label'] ?? ''),
            'identifier' => Theme::slug((string) ($this->theme['identifier'] ?? '')),
            'surfaces' => $this->normalizeSurfaces(is_array($this->theme['surfaces'] ?? null) ? $this->theme['surfaces'] : []),
            'css' => (string) ($this->theme['css'] ?? ''),
            'js' => (string) ($this->theme['js'] ?? ''),
        ];
    }

    public function apply(StageChange $change, array $snapshot): StageTargetResult
    {
        return match ($change->type()) {
            'theme.css.set' => $this->setCss($snapshot, $change->payload()),
            'theme.css.append' => $this->appendCss($snapshot, $change->payload()),
            'theme.js.set' => $this->setJs($snapshot, $change->payload()),
            'theme.meta.update' => $this->updateMeta($snapshot, $change->payload()),
            default => throw new \InvalidArgumentException(sprintf('Unsupported theme stage change "%s".', $change->type())),
        };
    }

    public function commit(Stage $stage): StageCommitResult
    {
        $snapshot = $stage->workingSnapshot();
        $identifier = Theme::slug((string) ($snapshot['identifier'] ?? ''));
        $themeId = $this->themes()->save([
            'id' => (int) ($snapshot['id'] ?? 0),
            'identifier' => $identifier,
            'label' => (string) ($snapshot['label'] ?? ''),
            'surfaces' => $this->normalizeSurfaces(is_array($snapshot['surfaces'] ?? null) ? $snapshot['surfaces'] : []),
            'css' => (string) ($snapshot['css'] ?? ''),
            'js' => (string) ($snapshot['js'] ?? ''),
        ]);

        $this->assets()->write($identifier, (string) ($snapshot['css'] ?? ''), (string) ($snapshot['js'] ?? ''));

        return StageCommitResult::success('Theme stage committed.', [
            'themeId' => $themeId,
            'identifier' => $identifier,
            'publishRequired' => false,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function setCss(array $snapshot, array $payload): StageTargetResult
    {
        $css = (string) ($payload['css'] ?? '');
        $snapshot['css'] = $css;

        return StageTargetResult::create($snapshot, [[
            'action' => 'themeEditor.setCss',
            'payload' => ['css' => $css],
        ]]);
    }

    /** @param array<string, mixed> $payload */
    private function appendCss(array $snapshot, array $payload): StageTargetResult
    {
        $css = trim((string) ($payload['css'] ?? ''));
        if ($css === '') {
            throw new \InvalidArgumentException('theme.css.append requires css.');
        }

        $snapshot['css'] = rtrim((string) ($snapshot['css'] ?? '')) . "\n\n" . $css . "\n";

        return StageTargetResult::create($snapshot, [[
            'action' => 'themeEditor.setCss',
            'payload' => ['css' => $snapshot['css']],
        ]]);
    }

    /** @param array<string, mixed> $payload */
    private function setJs(array $snapshot, array $payload): StageTargetResult
    {
        $js = (string) ($payload['js'] ?? '');
        $snapshot['js'] = $js;

        return StageTargetResult::create($snapshot, [[
            'action' => 'themeEditor.setJs',
            'payload' => ['js' => $js],
        ]]);
    }

    /** @param array<string, mixed> $payload */
    private function updateMeta(array $snapshot, array $payload): StageTargetResult
    {
        if (array_key_exists('label', $payload)) {
            $snapshot['label'] = trim((string) $payload['label']);
        }
        if (array_key_exists('identifier', $payload)) {
            $snapshot['identifier'] = Theme::slug((string) $payload['identifier']);
        }
        if (isset($payload['surfaces']) && is_array($payload['surfaces'])) {
            $snapshot['surfaces'] = $this->normalizeSurfaces($payload['surfaces']);
        }

        return StageTargetResult::create($snapshot, [[
            'action' => 'themeEditor.updateMeta',
            'payload' => [
                'label' => (string) ($snapshot['label'] ?? ''),
                'identifier' => (string) ($snapshot['identifier'] ?? ''),
                'surfaces' => is_array($snapshot['surfaces'] ?? null) ? $snapshot['surfaces'] : [],
            ],
        ]]);
    }

    private function themes(): ThemeRepository
    {
        return $this->themes ??= new ThemeRepository();
    }

    /**
     * @param array<mixed> $surfaces
     * @return array<string, string>
     */
    private function normalizeSurfaces(array $surfaces): array
    {
        $normalized = [];
        foreach ($surfaces as $key => $value) {
            if (is_array($value)) {
                $id = Theme::slug((string) ($value['key'] ?? $value['id'] ?? ''));
                $label = trim((string) ($value['label'] ?? ''));
            } else {
                $id = Theme::slug((string) $key);
                $label = trim((string) $value);
            }

            if ($id !== '' && $label !== '') {
                $normalized[$id] = $label;
            }
        }

        return $normalized;
    }

    private function assets(): ThemeAssetWriter
    {
        return $this->assets ??= new ThemeAssetWriter();
    }
}
