<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Admin;

use Naf\CMS\Services\Admin\AdminDashboardService;
use Psr\Http\Message\ResponseInterface;

use function Naf\json;

final class GetSummary
{
    public function __invoke(): ResponseInterface
    {
        return json((new AdminDashboardService())->summary());
    }
}
