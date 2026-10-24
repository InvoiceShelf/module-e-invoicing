<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EInvoicing\Support\Authorizes;

/** What the signed-in member may do here, so the frontend shows only that. */
final class CapabilitiesController
{
    public function __invoke(Request $request, Authorizes $authorizes): JsonResponse
    {
        return response()->json([
            'manage_settings' => $authorizes->canManageSettings($request),
            'view_invoices' => $authorizes->canViewInvoices($request),
        ]);
    }
}
