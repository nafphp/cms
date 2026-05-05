<?php

declare(strict_types=1);

return [
    ...require __DIR__ . '/Resources/api/admin.php',
    ...require __DIR__ . '/Resources/api/users.php',
    ...require __DIR__ . '/Resources/api/roles.php',
    ...require __DIR__ . '/Resources/api/articles.php',
    ...require __DIR__ . '/Resources/api/pages.php',
    ...require __DIR__ . '/Resources/api/navigation.php',
    ...require __DIR__ . '/Resources/api/forms.php',
    ...require __DIR__ . '/Resources/api/media.php',
    ...require __DIR__ . '/Resources/api/ai.php',
    ...require __DIR__ . '/Resources/api/settings.php',
];
