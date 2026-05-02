<?php

declare(strict_types=1);

use NixCMS\Content\Article\ArticleContentType;
use NixCMS\Content\Form\FormContentType;
use NixCMS\Content\Navigation\NavigationContentType;

return [
    new ArticleContentType(),
    new NavigationContentType(),
    new FormContentType(),
];
