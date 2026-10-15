<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Page\Theme;

use Naf\CMS\Repositories\ThemeRepository;
use Naf\CMS\Services\Theme\ThemeAssetWriter;
use Naf\CMS\Theme\Theme;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function Naf\redirect;
use function Naf\request;
use function Naf\route;

final class StoreTheme
{
    public function __invoke(): ResponseInterface
    {
        $request    = request();
        $data       = $request instanceof ServerRequestInterface ? $request->getParsedBody() : [];
        $data       = is_array($data) ? $data : [];
        $label      = trim((string) ($data['label'] ?? ''));
        $identifier = Theme::slug((string) ($data['identifier'] ?? $label));
        $css        = (string) ($data['css'] ?? '');
        $surfaces   = $this->surfaces(is_array($data['surfaces'] ?? null) ? $data['surfaces'] : []);

        if ($surfaces === []) {
            $surfaces = [
                'none'   => 'None',
                'soft'   => 'Soft',
                'card'   => 'Card',
                'accent' => 'Accent',
            ];
        }

        try {
            $id = (new ThemeRepository())->save([
                'id'         => (int) ($data['id'] ?? 0),
                'identifier' => $identifier,
                'label'      => $label,
                'surfaces'   => $surfaces,
                'css'        => $css,
                'js'         => '',
            ]);

            (new ThemeAssetWriter())->write($identifier, $css);

            return redirect(route('admin.themes.edit', ['themeId' => $id]));
        } catch (Throwable $exception) {
            return redirect(route('admin.themes.create') . '?error=' . rawurlencode($exception->getMessage()));
        }
    }

    /**
     * @return array<string, string>
     * @param array<array-key, mixed> $rows
     */
    private function surfaces(array $rows): array
    {
        $surfaces = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $id    = Theme::slug((string) ($row['key'] ?? $label));
            if ($id !== '') {
                $surfaces[$id] = $label === '' ? ucwords(str_replace('-', ' ', $id)) : $label;
            }
        }

        return $surfaces;
    }
}
