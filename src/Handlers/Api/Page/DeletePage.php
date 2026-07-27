<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Page;

use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\CMS\cms;

final class DeletePage
{
    public function __invoke(string $id): ResponseInterface
    {
        $pageId = (int) $id;
        if ($pageId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid page id.'], 400);
        }

        if (!cms()->pages()->delete($pageId)) {
            return json(['success' => false, 'reason' => 'Could not delete page.'], 400);
        }

        return json(['success' => true]);
    }
}
