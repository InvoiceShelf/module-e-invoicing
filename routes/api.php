<?php

use Illuminate\Support\Facades\Route;
use Modules\EInvoicing\Http\CapabilitiesController;
use Modules\EInvoicing\Http\InvoiceController;
use Modules\EInvoicing\Http\SettingsController;

/*
 * Behind the host's API stack, like every module route: a signed-in member of
 * the active company. Each controller checks its own ability.
 */
Route::prefix('api/v1/e-invoicing')->middleware(['api', 'auth:sanctum', 'company', 'bouncer'])->group(function (): void {
    Route::get('capabilities', CapabilitiesController::class)->name('e-invoicing.capabilities');
    Route::get('settings', [SettingsController::class, 'show'])->name('e-invoicing.settings.show');
    Route::put('settings', [SettingsController::class, 'update'])->name('e-invoicing.settings.update');
    Route::get('invoices/{invoice}/readiness', [InvoiceController::class, 'readiness'])->whereNumber('invoice')->name('e-invoicing.invoices.readiness');
    Route::get('invoices/{invoice}/xml', [InvoiceController::class, 'xml'])->whereNumber('invoice')->name('e-invoicing.invoices.xml');
});
