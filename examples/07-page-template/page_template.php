<?php

declare(strict_types=1);

use Naf\CMS\Page\Layout;
use Naf\CMS\Page\Template;

use function Naf\CMS\cms;

// The view resolves to src/views/page/templates/example_split.phtml in the
// plugin; NAF adds a plugin's src/views directory to the view path.
cms()->pageTemplates()->add(
    Layout::define('example-split', 'Example split', Template::create('example_split')->setParent('page'))
        ->setDescription('Intro across the full width, then content with a narrow aside.')
        ->addStylesheet('/css/example-split.css')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('intro', 'page.blocks.empty', ['label' => 'Intro', 'region' => 'main', 'size' => 'medium', 'preview' => 'heading'])
        ->addBlock('content', 'page.blocks.empty', ['label' => 'Content', 'region' => 'main', 'size' => 'large'])
        ->addBlock('aside', 'page.blocks.empty', ['label' => 'Aside', 'region' => 'aside', 'size' => 'aside'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),
    replace: true,
);
