<?php

declare(strict_types=1);

use NixCMS\Navigation\NavigationItem;

return [
    NavigationItem::create('content')
        ->setLabel('Content')
        ->setHref('#')
        ->setIcon('content')
        ->setOrder(20)
        ->setScope('admin'),
    NavigationItem::create('settings')
        ->setLabel('Settings')
        ->setHref('#')
        ->setIcon('settings')
        ->setOrder(90)
        ->setScope('admin'),
];
