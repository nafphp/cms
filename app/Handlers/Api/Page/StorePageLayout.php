<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Page;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\PageLayoutRepository;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class StorePageLayout
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $repository = new PageLayoutRepository();

        try {
            $layout = $repository->save([
                'id' => (int) ($data['id'] ?? 0),
                'name' => (string) ($data['name'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'layout' => 'layouts.page',
                'view' => 'page.templates.custom',
                'slots' => is_array($data['slots'] ?? null) ? $data['slots'] : [],
                'meta' => [
                    'source' => 'page-builder',
                ],
            ]);

            return json([
                'success' => true,
                'layout' => $layout,
                'template' => $repository->toTemplate($layout),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }
}
