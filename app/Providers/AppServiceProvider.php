<?php

namespace App\Providers;

use App\Support\CsvImport\DefaultPaymentInstrumentNumberOmitter;
use App\Support\CsvImport\PaymentInstrumentNumberOmitter;
use App\Support\SheetLoad\SheetMapping;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            PaymentInstrumentNumberOmitter::class,
            DefaultPaymentInstrumentNumberOmitter::class,
        );

        $this->app->singleton(SheetMapping::class, fn (): SheetMapping => SheetMapping::fromFile());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        $this->configureFrontDoorUrls();
    }

    /**
     * Traefik terminates TLS, so PHP sees HTTP. Generated redirects must still be HTTPS.
     */
    protected function configureFrontDoorUrls(): void
    {
        $appUrl = config('app.url');

        if (! is_string($appUrl) || ! str_starts_with($appUrl, 'https://')) {
            return;
        }

        URL::forceRootUrl(rtrim($appUrl, '/'));
        URL::forceScheme('https');
    }
}
