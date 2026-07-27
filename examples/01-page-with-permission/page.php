<?php

declare(strict_types=1);

use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Page\Page;
use function NixPHP\View\view;
use function NixPHP\CMS\cms;

cms()->pages()->add(
    Page::create('example.reports')
        ->setPath('/admin/examples/reports')
        ->setTitle('Example reports')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_VIEW)
        ->setContent(static fn(): string => view('admin.examples.reports')),
    replace: true
);
