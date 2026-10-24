<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Demo;

use Illuminate\Database\Seeder;
use Modules\EInvoicing\Application\EInvoicingSettings;

/**
 * E-invoicing switched on for the public demo, with the bank details and
 * payment terms an e-invoice asks for, so the demo's invoices show the
 * module at work as soon as it opens.
 *
 * The host's demo reset calls run() once the demo company exists, passing
 * the company to fill. A company that already has an IBAN is left alone.
 */
final class DemoSeeder extends Seeder
{
    /** The IBAN and BIC used as examples everywhere, not a real account. */
    public const IBAN = 'DE89370400440532013000';

    public const BIC = 'COBADEFFXXX';

    public const PAYMENT_TERMS = 'Payable within 14 days without deduction.';

    public function run(int $companyId, EInvoicingSettings $settings): void
    {
        if ($settings->iban($companyId) !== null) {
            return;
        }

        $settings->save($companyId, [
            'enabled' => true,
            'iban' => self::IBAN,
            'bic' => self::BIC,
            'payment_terms' => self::PAYMENT_TERMS,
        ]);
    }
}
