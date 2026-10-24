# Contributing

## Checks

The same as CI:

```bash
composer install
vendor/bin/invoiceshelf-module bundle . --host-lock=<host composer.lock>   # builds bundle/ from bundle.lock
composer run lint
composer run test
MUSTANG_JAR=<Mustang-CLI-2.25.0.jar> vendor/bin/phpunit --group schematron
vendor/bin/invoiceshelf-module validate-module module.json
vendor/bin/invoiceshelf-module validate-package .

pnpm install --frozen-lockfile
pnpm run lint
pnpm exec tsc --noEmit
pnpm run build          # commit dist/: the host never runs Node
```

PHP 8.4, Java 21, Node 24, pnpm 11. Mustang CLI is at https://github.com/ZUGFeRD/mustangproject/releases.

A change to what the XML contains needs a test in `tests/Unit/CiiInvoiceBuilderTest.php`, and a new kind of valid invoice belongs in `tests/Support/Scenarios.php`, so the schematron run checks it too. Bump `EInvoiceBuilder::OUTPUT_VERSION` whenever the output changes, so cached XML is rebuilt.

## Releasing

Set `version` in `module.json` and `package.json`, rebuild `dist/`, check `host-version` in `.github/workflows/release.yml` is the oldest InvoiceShelf release the version supports, merge, then push an unprefixed tag equal to the version (`0.1.0`, not `v0.1.0`).
