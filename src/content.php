<?php

declare(strict_types=1);

use NixPHP\CMS\Content\Article\ArticleContentType;
use NixPHP\CMS\Content\Form\FormContentType;
use NixPHP\CMS\Content\Media\MediaContentType;
use NixPHP\CMS\Content\Navigation\NavigationContentType;

return [
    new ArticleContentType(),
    new NavigationContentType(),
    new FormContentType(),
    new MediaContentType(),
];
