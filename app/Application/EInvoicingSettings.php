<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Application;

use InvoiceShelf\Modules\Contracts\Host\SettingsStore;

/**
 * The module's per-company settings, kept in the host's company settings
 * under `module.e-invoicing.*`.
 *
 * - `enabled`: invoice PDFs of this company carry the e-invoice XML.
 * - `iban`, `bic`: the payment account written as the credit transfer
 *   (BG-16, BT-84, BT-86). Stored normalised, without spaces, upper case.
 * - `payment_terms`: the payment terms text (BT-20), written on every invoice
 *   and required on one without a due date (BR-CO-25).
 */
final class EInvoicingSettings
{
    public const PREFIX = 'module.e-invoicing.';

    public const ENABLED = 'enabled';

    public const IBAN = 'iban';

    public const BIC = 'bic';

    public const PAYMENT_TERMS = 'payment_terms';

    /** Every key the module stores, for the uninstall cleanup. */
    public const KEYS = [self::ENABLED, self::IBAN, self::BIC, self::PAYMENT_TERMS];

    public function __construct(private readonly SettingsStore $store) {}

    public function isEnabled(int $companyId): bool
    {
        return $this->store->getCompany($companyId, self::PREFIX.self::ENABLED) === 'YES';
    }

    public function iban(int $companyId): ?string
    {
        return self::bankIdentifier($this->store->getCompany($companyId, self::PREFIX.self::IBAN));
    }

    public function bic(int $companyId): ?string
    {
        return self::bankIdentifier($this->store->getCompany($companyId, self::PREFIX.self::BIC));
    }

    public function paymentTerms(int $companyId): ?string
    {
        $terms = trim((string) $this->store->getCompany($companyId, self::PREFIX.self::PAYMENT_TERMS));

        return $terms === '' ? null : $terms;
    }

    /** @return array{enabled: bool, iban: ?string, bic: ?string, payment_terms: ?string} */
    public function forCompany(int $companyId): array
    {
        return [
            'enabled' => $this->isEnabled($companyId),
            'iban' => $this->iban($companyId),
            'bic' => $this->bic($companyId),
            'payment_terms' => $this->paymentTerms($companyId),
        ];
    }

    /** @param  array{enabled: bool, iban?: ?string, bic?: ?string, payment_terms?: ?string}  $values */
    public function save(int $companyId, array $values): void
    {
        $this->store->putCompany($companyId, self::PREFIX.self::ENABLED, $values['enabled'] ? 'YES' : 'NO');

        foreach ([self::IBAN, self::BIC] as $key) {
            $this->put($companyId, $key, self::bankIdentifier($values[$key] ?? null));
        }

        $terms = trim((string) ($values[self::PAYMENT_TERMS] ?? ''));
        $this->put($companyId, self::PAYMENT_TERMS, $terms === '' ? null : $terms);
    }

    /** A bank identifier as it is written: no spacing, upper case; null when empty. */
    public static function bankIdentifier(mixed $value): ?string
    {
        $identifier = strtoupper((string) preg_replace('/\s+/', '', (string) $value));

        return $identifier === '' ? null : $identifier;
    }

    private function put(int $companyId, string $key, ?string $value): void
    {
        if ($value === null) {
            $this->store->deleteCompany($companyId, self::PREFIX.$key);

            return;
        }

        $this->store->putCompany($companyId, self::PREFIX.$key, $value);
    }
}
