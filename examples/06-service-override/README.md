# Service Override

NafCMS binds replaceable behavior to contracts during the plugin bootstrap.
A host makes the same `app()->container()->set()` call in its `bootstrap.php`
before `app()->run()`. The first `app()` call has booted the plugins by then,
so the host binding replaces the CMS default.

This example replaces local media storage with a small illustrative adapter.
A production implementation can use S3, a CDN, or another storage service while
the CMS continues to depend only on `MediaStorageInterface`.
