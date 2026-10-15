# CMS quality checks

Run the declared checks from the CMS package with its supported PHP runtime and extensions:

```bash
composer install
composer validate --strict
composer check
composer style:check
```

`composer check` runs PHPStan and the PHPUnit suite. PHPStan analyses `src/` and `tests/`
at level 6 without a baseline. Migrations remain outside that analysis scope; their execution
and rollback are covered separately by the package migration checks. Example block classes
and the navigation template helper are scanned for symbols without booting their runtime code.

Keep PHPDoc collection types consistent with actual values. Use entity generics for
`EntityStorage<T>` so `find()`, `make()`, `search()` and `matching()` retain the model type.
Repository-backed `ContentApi` lists contain entities; they do not return a single entity.
Put each PHPDoc tag on its own line so parameters and return shapes are all recognised.

The framework request accessor can return a basic PSR-7 request. Check for
`ServerRequestInterface` before reading parsed input, uploads, query parameters or server
metadata. Parsed bodies can also be objects: login and form handlers must establish their
expected array shape before accessing fields. LanguageRegistry returns normalised string
codes, including configured languages; pass those strings to article actions directly.

Run the installed-host checks when changing HTTP handling, installation or boot behaviour:

```bash
scripts/check-package.sh
scripts/package-smoke.sh
```

The smoke script installs a fresh mirrored package into `naf/app`, runs `install:database`
twice against its disposable SQLite database and checks the real authenticated HTTP flow.
The repeat installation must succeed without reapplying migrations. The host's files and
server are cleaned up after the run; no development or production database is used.

Article action regression tests cover successful publish and delete requests with normalised
language codes and retain the refusal to delete published versions. Login regressions cover
object parsed bodies and requests without server-request capabilities. A failure must be
fixed in the owning workflow and covered there, rather than hidden in the analysis settings.
