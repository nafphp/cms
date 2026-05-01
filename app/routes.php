<?php

declare(strict_types=1);

cms()->pages()->addMany(require __DIR__ . '/pages.php');
cms()->api()->addMany(require __DIR__ . '/api.php');

cms()->pages()->registerRoutes();
cms()->api()->registerRoutes();
