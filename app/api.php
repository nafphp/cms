<?php

declare(strict_types=1);

return [
    ...require __DIR__ . '/Resources/api/admin.php',
    ...require __DIR__ . '/Resources/api/users.php',
    ...require __DIR__ . '/Resources/api/roles.php',
    ...require __DIR__ . '/Resources/api/articles.php',
];
