<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Theme;

use InvalidArgumentException;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Repositories\ThemeRepository;
use Naf\CMS\Services\Theme\ThemeAssetWriter;
use Naf\CMS\Theme\Theme;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function Naf\json;

final class StoreTheme
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data       = $this->jsonBody();
        $repository = new ThemeRepository();
        $identifier = Theme::slug((string) ($data['identifier'] ?? $data['label'] ?? ''));
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
            $id = $repository->save([
                'id'         => (int) ($data['id'] ?? 0),
                'identifier' => $identifier,
                'label'      => (string) ($data['label'] ?? ''),
                'surfaces'   => $surfaces,
                'css'        => $css,
                'js'         => '',
            ]);

            (new ThemeAssetWriter())->write($identifier, $css);

            $theme = $repository->find($id) ?? [];

            return json([
                'success' => true,
                'theme'   => [
                    'id'         => (int) ($theme['id'] ?? $id),
                    'identifier' => (string) ($theme['identifier'] ?? $identifier),
                    'label'      => (string) ($theme['label'] ?? $identifier),
                    'surfaces'   => is_array($theme['surfaces'] ?? null) ? $theme['surfaces'] : $surfaces,
                    'css'        => (string) ($theme['css'] ?? $css),
                    'js'         => '',
                    'cssPath'    => (string) ($theme['cssPath'] ?? ('/css/themes/' . $identifier . '.css')),
                    'jsPath'     => '',
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            error_log((string) $exception);

            return json(['success' => false, 'reason' => 'Theme could not be saved.'], 500);
        }
    }

    /**
     * @return array<string, string>
     * @param array<array-key, mixed> $rows
     */
    private function surfaces(array $rows): array
    {
        $surfaces = [];
        foreach ($rows as $key => $row) {
            if (is_array($row)) {
                $label = trim((string) ($row['label'] ?? ''));
                $id    = Theme::slug((string) ($row['key'] ?? $row['value'] ?? $key));
            } else {
                $label = trim((string) $row);
                $id    = Theme::slug((string) $key);
            }

            if ($id !== '') {
                $surfaces[$id] = $label === '' ? ucwords(str_replace('-', ' ', $id)) : $label;
            }
        }

        return $surfaces;
    }
}
