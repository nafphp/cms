<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Services\Admin\AdminDashboardService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\View\render;

final class DashboardController
{
    public function index(): ResponseInterface
    {
        return render('admin.dashboard', [
            'title' => 'Dashboard',
            'summary' => (new AdminDashboardService())->summary(),
        ]);
    }
}
