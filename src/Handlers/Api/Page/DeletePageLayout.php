<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Page;

use Naf\CMS\Repositories\PageLayoutRepository;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function Naf\json;

final class DeletePageLayout
{
    public function __invoke(string $id): ResponseInterface
    {
        $layoutId = (int) $id;
        if ($layoutId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid layout id.'], 400);
        }

        $repository = new PageLayoutRepository();

        try {
            if (!$repository->delete($layoutId)) {
                return json([
                    'success' => false,
                    'reason' => $repository->lastError() ?? 'Layout could not be deleted.',
                ], 400);
            }

            return json(['success' => true]);
        } catch (Throwable $exception) {
            error_log($exception);
            return json(['success' => false, 'reason' => 'Layout could not be deleted.'], 500);
        }
    }
}
