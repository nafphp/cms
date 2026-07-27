# Complete Plugin Skeleton

This compact example shows the explicit registration path for a Composer package of type `nixphp-plugin`: `bootstrap.php` registers a protected Page, API endpoint, migration path, and scoped MCP tool. NixPHP discovers the package's `src/views` directory by convention; the Page references that view explicitly.

In a real package, map `ExampleVendor\\ReportPlugin\\` to `src/` with Composer PSR-4 autoloading and split the classes from `plugin.php` into matching files. Keep NixCMS after its NixPHP dependencies and this plugin after NixCMS in the host's `app/plugins.php`.
