<?php

declare(strict_types=1);

use NixCMS\Page\Page;
use function NixPHP\View\render;

return [
    Page::create('home')
        ->setPath('/')
        ->setTitle(cms()->settings()->name())
        ->setContent(static fn() => render('welcome.index', [
            'title' => cms()->settings()->name(),
        ])),
];
