<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use InvoiceShelf\Modules\Contracts\Host\ModuleAuthorization;
use InvoiceShelf\Modules\Registry;

/**
 * Ability checks against the host for the request's user and company. A
 * refusal raises AuthorizationException, which the framework renders as 403.
 */
final class Authorizes
{
    public const SLUG = 'e-invoicing';

    /** Change the company's e-invoicing settings. Owner only. */
    public const MANAGE_SETTINGS = 'manage-settings';

    public function __construct(private readonly ModuleAuthorization $authorization) {}

    public static function companyId(Request $request): int
    {
        return (int) $request->header('company');
    }

    public function canManageSettings(Request $request): bool
    {
        return $this->allows($request, Registry::abilityId(self::SLUG, self::MANAGE_SETTINGS));
    }

    public function canViewInvoices(Request $request): bool
    {
        return $this->allows($request, 'view-invoice', 'invoice');
    }

    /** @throws AuthorizationException */
    public function requireSettings(Request $request): void
    {
        if (! $this->canManageSettings($request)) {
            throw new AuthorizationException('Managing e-invoicing settings requires the '.Registry::abilityId(self::SLUG, self::MANAGE_SETTINGS).' ability.');
        }
    }

    /** @throws AuthorizationException */
    public function requireInvoices(Request $request): void
    {
        if (! $this->canViewInvoices($request)) {
            throw new AuthorizationException('This requires the view-invoice ability.');
        }
    }

    private function allows(Request $request, string $ability, ?string $resource = null): bool
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $userId !== null && $this->authorization->allows((int) $userId, self::companyId($request), $ability, $resource);
    }
}
