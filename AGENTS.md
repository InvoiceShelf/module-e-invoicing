# AGENTS.md

Guide for coding agents working on the official E-Invoicing module for InvoiceShelf 3.

- **What it is.** EN 16931 e-invoices: invoice PDFs become ZUGFeRD / Factur-X hybrids and the CII XML downloads on its own. Read the README first.
- **Pipeline.** The host's `InvoiceDocumentReader` hands over an `InvoiceDocument` (SDK typed data, amounts in stored hundredths, credit notes negative). `Document\En16931Mapper` turns it into a syntax-neutral `En16931Invoice` or a list of `Application\Requirement`s; `Document\Writers\CiiWriter` writes CII with horstoeko/zugferd and XSD-validates it. `Application\EInvoiceBuilder` caches written XML by a hash of everything it was built from. A UBL writer would sit next to `CiiWriter` and read the same `En16931Invoice`.
- **Host seams (module API 1.5).** `Pdf\FacturXAttachmentProvider` is registered with `Registry::registerInvoicePdfAttachmentProvider`; the host asks it on every invoice PDF when `PdfCapabilities::embedsFacturX()` is true. Never read host config or models: only SDK contracts.
- **Requirements are stable identifiers.** `Requirement` values are translated in `resources/js/messages.ts` and asserted in tests; never rename one. A new rule gets a new case, a message and a test.
- **Settings** go through `EInvoicingSettings` (keys `module.e-invoicing.*`); `Lifecycle\DataCleanup` must list every key.
- **Frontend.** `resources/js/init.ts` registers the settings page, the invoice panel and the Download XML action. Call the API only through `extensions.client`, ship CSS from `resources/css/module.css` (host utilities are not available), and do not import axios at runtime (it would be bundled). Rebuild and commit `dist/` after any change under `resources/`.
- **Bundle.** `bundle/` is generated from the committed `bundle.lock`; never edit it, and keep tools out of it.
- **Checks.** `composer run lint`, `composer run test`, `MUSTANG_JAR=… vendor/bin/phpunit --group schematron`, `vendor/bin/invoiceshelf-module validate-package .`, `pnpm run lint`, `pnpm exec tsc --noEmit`, `pnpm run build`. CI fails when `dist/` differs from a fresh build or the fixture differs from the host's.
