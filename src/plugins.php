<?php

declare(strict_types=1);

// Plugin boot order for when this package is the base path, i.e. its own test
// suite. Hosts define their own plugins.php; this file is not read there.
//
// NixCMS must come last: it consumes services the plugins above it register,
// and would otherwise have its own registrations overwritten again.
return [
    'nixphp/cli',
    'nixphp/database',
    'nixphp/session',
    'nixphp/auth',
    'nixphp/form',
    'nixphp/view',
    'nixphp/i18n',
    'nixphp/orm',
    'nixphp/queue',
    'nixphp/schedule',
    'nixphp/mail',
    'nixphp/mcp',
    'nixphp/cms',
];
