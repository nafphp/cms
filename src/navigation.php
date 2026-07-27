<?php

declare(strict_types=1);

use src\Navigation\NavigationItem;
use src\Navigation\Navigation;

return [
    Navigation::create('main', 'Main navigation')
        ->addItem(
            NavigationItem::create('home')
                ->setLabel('Home')
                ->setHref('/')
                ->setOrder(10)
        )
        ->addItem(
            NavigationItem::create('articles')
                ->setLabel('Articles')
                ->setHref('/articles')
                ->setOrder(20)
        ),
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
    NavigationItem::create('settings.ai')
        ->setLabel('Local AI')
        ->setHref('/admin/settings/ai')
        ->setParent('settings')
        ->setIcon('ai')
        ->setOrder(25)
        ->setScope('admin'),
];
