<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Admin;

use NixCMS\Services\Admin\AdminDashboardService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class GetSummary
{
    public function __invoke(): ResponseInterface
    {
        return json((new AdminDashboardService())->summary());
    }
}
