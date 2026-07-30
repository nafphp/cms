# Service Override

NixCMS binds replaceable behavior to contracts during the plugin bootstrap.
The host loads `app/src/services.php` from its routes file after plugin
bootstraps, so its binding wins.

This example replaces local media storage with a small illustrative adapter.
A production implementation can use S3, a CDN, or another storage service while
the CMS continues to depend only on `MediaStorageInterface`.
