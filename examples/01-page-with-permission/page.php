<?php

declare(strict_types=1);

use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;
use function Naf\View\view;
use function Naf\CMS\cms;

cms()->pages()->add(
    Page::create('example.reports')
        ->setPath('/admin/examples/reports')
        ->setTitle('Example reports')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_VIEW)
        ->setContent(static fn(): string => view('admin.examples.reports')),
    replace: true
);
