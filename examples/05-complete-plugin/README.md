# Complete Plugin Skeleton

This compact example shows the explicit registration path for a Composer package of type `naf-plugin`: `bootstrap.php` registers a protected Page, API endpoint, migration path, and scoped MCP tool. NAF discovers the package's `src/views` directory by convention; the Page references that view explicitly.

In a real package, map `ExampleVendor\\ReportPlugin\\` to `src/` with Composer PSR-4 autoloading and split the classes from `plugin.php` into matching files. Keep NafCMS after its NAF dependencies and this plugin after NafCMS in the host's `app/plugins.php`.
