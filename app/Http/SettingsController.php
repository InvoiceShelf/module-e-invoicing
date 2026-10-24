<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EInvoicing\Application\EInvoicingSettings;
use Modules\EInvoicing\Application\Readiness;
use Modules\EInvoicing\Http\Requests\SettingsRequest;
use Modules\EInvoicing\Support\Authorizes;

final class SettingsController
{
    public function __construct(
        private readonly EInvoicingSettings $settings,
        private readonly Readiness $readiness,
        private readonly Authorizes $authorizes,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizes->requireSettings($request);

        return $this->respond(Authorizes::companyId($request));
    }

    public function update(SettingsRequest $request): JsonResponse
    {
        $companyId = Authorizes::companyId($request);

        $this->settings->save($companyId, [
            'enabled' => $request->boolean('enabled'),
            'iban' => $request->validated('iban'),
            'bic' => $request->validated('bic'),
            'payment_terms' => $request->validated('payment_terms'),
        ]);

        return $this->respond($companyId);
    }

    private function respond(int $companyId): JsonResponse
    {
        return response()->json([
            'settings' => $this->settings->forCompany($companyId),
            'readiness' => $this->readiness->forCompany($companyId),
        ]);
    }
}
