<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use function NixPHP\View\view;
use function NixCMS\cms;

cms()->pages()->add(
    Page::create('example.reports')
        ->setPath('/admin/examples/reports')
        ->setTitle('Example reports')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_VIEW)
        ->setContent(static fn(): string => view('admin.examples.reports')),
    replace: true
);
