<?php

declare(strict_types=1);

use Naf\CMS\Visualization\PageChartFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// A standalone factory can use the host's translator before NAF plugins boot.
function t(string $value): string
{
    return 'translated: ' . $value;
}

$chart = (new PageChartFactory())->fromBlockProps([
    'title'        => 'Publishing',
    'datasetLabel' => 'Articles',
    'metricCount'  => 1,
    'label1'       => 'Draft',
    'value1'       => 3,
]);

echo json_encode([$chart->title, $chart->datasets[0]->label], JSON_THROW_ON_ERROR);
