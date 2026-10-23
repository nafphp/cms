# Content Events

Register listeners for successful Content API changes through `CmsEvent` constants.
`listener.php` logs the page id after publication without performing a write.
Listeners receive before/after snapshots in a readonly `ContentEvent`.
Listener exceptions are logged and do not undo the completed CMS change.
