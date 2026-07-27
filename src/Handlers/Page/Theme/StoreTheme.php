<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Page\Theme;

use NixPHP\CMS\Repositories\ThemeRepository;
use NixPHP\CMS\Services\Theme\ThemeAssetWriter;
use NixPHP\CMS\Theme\Theme;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\route;

final class StoreTheme
{
    public function __invoke(): ResponseInterface
    {
        $data = request()->getParsedBody() ?? [];
        $data = is_array($data) ? $data : [];
        $label = trim((string) ($data['label'] ?? ''));
        $identifier = Theme::slug((string) ($data['identifier'] ?? $label));
        $css = (string) ($data['css'] ?? '');
        $surfaces = $this->surfaces(is_array($data['surfaces'] ?? null) ? $data['surfaces'] : []);

        if ($surfaces === []) {
            $surfaces = [
                'none' => 'None',
                'soft' => 'Soft',
                'card' => 'Card',
                'accent' => 'Accent',
            ];
        }

        try {
            $id = (new ThemeRepository())->save([
                'id' => (int) ($data['id'] ?? 0),
                'identifier' => $identifier,
                'label' => $label,
                'surfaces' => $surfaces,
                'css' => $css,
                'js' => '',
            ]);

            (new ThemeAssetWriter())->write($identifier, $css);

            return redirect(route('admin.themes.edit', ['themeId' => $id]));
        } catch (Throwable $exception) {
            return redirect(route('admin.themes.create') . '?error=' . rawurlencode($exception->getMessage()));
        }
    }

    /**
     * @return array<string, string>
     */
    private function surfaces(array $rows): array
    {
        $surfaces = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $id = Theme::slug((string) ($row['key'] ?? $label));
            if ($id !== '') {
                $surfaces[$id] = $label === '' ? ucwords(str_replace('-', ' ', $id)) : $label;
            }
        }

        return $surfaces;
    }
}
