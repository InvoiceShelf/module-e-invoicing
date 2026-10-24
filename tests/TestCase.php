<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvoiceShelf\Modules\Contracts\Host\InvoiceDocumentReader;
use InvoiceShelf\Modules\Contracts\Host\ModuleAuthorization;
use InvoiceShelf\Modules\Contracts\Host\PdfCapabilities;
use InvoiceShelf\Modules\Contracts\Host\SettingsStore;
use InvoiceShelf\Modules\InvoiceShelfModulesServiceProvider;
use InvoiceShelf\Modules\Registry;
use Modules\EInvoicing\Application\EInvoicingSettings;
use Modules\EInvoicing\Tests\Support\FakeAuthorization;
use Modules\EInvoicing\Tests\Support\FakeDocumentReader;
use Modules\EInvoicing\Tests\Support\FakePdfCapabilities;
use Modules\EInvoicing\Tests\Support\MemorySettingsStore;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * The module on Orchestra Testbench, with the host's contracts played by
 * in-memory fakes and the host's `company`, `bouncer` and `auth:sanctum`
 * middleware by stand-ins that let a request through as user 1 of company 1.
 */
abstract class TestCase extends Orchestra
{
    public const COMPANY = 1;

    public const IBAN = 'DE02120300000000202051';

    protected MemorySettingsStore $settings;

    protected FakeDocumentReader $reader;

    protected FakeAuthorization $authorization;

    protected FakePdfCapabilities $capabilities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new MemorySettingsStore;
        $this->reader = new FakeDocumentReader;
        $this->authorization = new FakeAuthorization;
        $this->capabilities = new FakePdfCapabilities;

        $this->app->instance(SettingsStore::class, $this->settings);
        $this->app->instance(InvoiceDocumentReader::class, $this->reader);
        $this->app->instance(ModuleAuthorization::class, $this->authorization);
        $this->app->instance(PdfCapabilities::class, $this->capabilities);
    }

    protected function tearDown(): void
    {
        Registry::flush();
        Registry::flushDrivers();

        parent::tearDown();
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [InvoiceShelfModulesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.guards.sanctum', ['driver' => 'module-test']);

        Auth::viaRequest('module-test', static fn (Request $request): ?GenericUser => $request->header('Authorization') === 'Bearer member' ? new GenericUser(['id' => 1]) : null);

        $app['router']->aliasMiddleware('company', PassThroughMiddleware::class);
        $app['router']->aliasMiddleware('bouncer', PassThroughMiddleware::class);

        $app['translator']->addNamespace('einvoicing', dirname(__DIR__).'/lang');
    }

    protected function defineRoutes($router): void
    {
        require dirname(__DIR__).'/routes/api.php';
    }

    /** The settings a ready German seller has. */
    protected function configureSeller(bool $enabled = true, ?string $iban = self::IBAN): void
    {
        $settings = new EInvoicingSettings($this->settings);
        $settings->save(self::COMPANY, ['enabled' => $enabled, 'iban' => $iban, 'bic' => 'BYLADEM1001', 'payment_terms' => null]);
    }

    /** @return array<string, string> */
    protected function member(): array
    {
        return ['Authorization' => 'Bearer member', 'company' => (string) self::COMPANY, 'Accept' => 'application/json'];
    }
}

/** Stands in for the host's company and bouncer middleware. */
final class PassThroughMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
