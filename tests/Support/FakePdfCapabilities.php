<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Support;

use InvoiceShelf\Modules\Contracts\Host\PdfCapabilities;

final class FakePdfCapabilities implements PdfCapabilities
{
    public bool $capable = true;

    public function embedsFacturX(): bool
    {
        return $this->capable;
    }
}
