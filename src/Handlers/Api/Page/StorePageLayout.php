<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Page;

use InvalidArgumentException;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Repositories\PageLayoutRepository;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function Naf\json;

final class StorePageLayout
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data       = $this->jsonBody();
        $repository = new PageLayoutRepository();

        try {
            $layout = $repository->save([
                'id'          => (int) ($data['id'] ?? 0),
                'name'        => (string) ($data['name'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'layout'      => 'layouts.page',
                'view'        => 'page.templates.custom',
                'slots'       => is_array($data['slots'] ?? null) ? $data['slots'] : [],
                'meta'        => [
                    'source' => 'page-builder',
                ],
            ]);

            return json([
                'success'  => true,
                'layout'   => $layout,
                'template' => $repository->toTemplate($layout),
            ]);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            error_log((string) $exception);

            return json(['success' => false, 'reason' => 'Layout could not be saved.'], 500);
        }
    }
}
