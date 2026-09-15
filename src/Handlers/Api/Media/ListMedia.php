<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Media;

use Psr\Http\Message\ResponseInterface;

use function Naf\CMS\cms;
use function Naf\json;
use function Naf\request;

final class ListMedia
{
    public function __invoke(): ResponseInterface
    {
        $query = request()->getQueryParams();
        $limit = max(1, min(500, (int) ($query['limit'] ?? 120)));

        return json([
            'rows'    => cms()->media()->recent($limit),
            'summary' => cms()->media()->summary(),
        ]);
    }
}
