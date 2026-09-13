<?php

declare(strict_types=1);

// Plugin boot order for when this package is the base path, i.e. its own test
// suite. Hosts define their own plugins.php; this file is not read there.
//
// NafCMS must come last: it consumes services the plugins above it register,
// and would otherwise have its own registrations overwritten again.
return [
    'naf/cli',
    'naf/database',
    'naf/session',
    'naf/auth',
    'naf/form',
    'naf/view',
    'naf/i18n',
    'naf/orm',
    'naf/queue',
    'naf/schedule',
    'naf/mail',
    'naf/mcp',
    'naf/cms',
];
