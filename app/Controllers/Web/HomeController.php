<?php

declare(strict_types=1);

namespace Cms\Controllers\Web;

use Psr\Http\Message\ResponseInterface;
use function NixPHP\View\render;

final class HomeController
{
    public function index(): ResponseInterface
    {
        return render('welcome.index', [
            'title' => 'NixCMS',
        ]);
    }
}
