<?php

namespace App\Providers;

use App\Support\ApplicationHealth;
use App\Support\CsvImport\DefaultPaymentInstrumentNumberOmitter;
use App\Support\CsvImport\PaymentInstrumentNumberOmitter;
use App\Support\OAuthConsentProps;
use App\Support\RehearsalClock;
use App\Support\SheetLoad\SheetMapping;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Passport\Passport;

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

        Event::listen(DiagnosingHealth::class, ApplicationHealth::class);

        $this->configurePassport();
        $this->configureMcpRateLimit();
        $this->configureErrorTunnelRateLimit();
    }

    /**
     * Passport is the OAuth server of the MCP endpoint (ADR-0110): short access tokens, Inertia consent page.
     * `mcp:use` is the default scope, so a client that asks without `scope` still gets a token `/mcp` accepts;
     * the authorization server reads it when it is first resolved, after boot.
     */
    protected function configurePassport(): void
    {
        Passport::defaultScopes(['mcp:use']);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));

        Passport::authorizationView(fn (array $parameters) => Inertia::render(
            'auth/OAuthAuthorize',
            OAuthConsentProps::fromAuthorization($parameters),
        )->toResponse(request()));
    }

    /**
     * 60 MCP calls a minute per signed-in user.
     */
    protected function configureMcpRateLimit(): void
    {
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }

    /**
     * The browser error tunnel is open to guests, so it is limited by IP (30 a minute by default).
     */
    protected function configureErrorTunnelRateLimit(): void
    {
        RateLimiter::for('error-tunnel', fn (Request $request): Limit => Limit::perMinute(
            (int) config('error-tracking.tunnel.requests_per_minute'),
        )->by((string) $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        RehearsalClock::apply();

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
