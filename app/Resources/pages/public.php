<?php

declare(strict_types=1);

use NixCMS\Page\Page;
use function NixPHP\View\render;

return [
    Page::create('home')
        ->setPath('/')
        ->setTitle('NixCMS')
        ->setContent(static fn() => render('welcome.index', [
            'title' => 'NixCMS',
        ])),
];
