# Content Events

Register listeners for successful content changes with `event()->listen()` and the `CmsEvent` constants.
`listener.php` logs the page id after publication without performing a write.
Listeners receive before/after snapshots in a readonly `ContentEvent`.
Each listener runs isolated: an exception is logged, does not undo the CMS change and does not stop the other listeners.
