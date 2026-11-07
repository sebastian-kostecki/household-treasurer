<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthKitchenSinkController extends Controller
{
    /**
     * Entry screens (screen key => page, label, states); the fixtures below are the props of each state.
     *
     * @var array<string, array{page: string, label: string, states: list<string>}>
     */
    private const SCREENS = [
        'login' => ['page' => 'auth/Login', 'label' => 'Logowanie', 'states' => ['default', 'status']],
        'register' => ['page' => 'auth/Register', 'label' => 'Rejestracja', 'states' => ['default']],
        'forgot-password' => ['page' => 'auth/ForgotPassword', 'label' => 'Nie pamiętam hasła', 'states' => ['default', 'status']],
        'reset-password' => ['page' => 'auth/ResetPassword', 'label' => 'Ustawienie nowego hasła', 'states' => ['default', 'no-email']],
        'verify-email' => ['page' => 'auth/VerifyEmail', 'label' => 'Weryfikacja adresu e-mail', 'states' => ['default', 'status']],
        'confirm-password' => ['page' => 'auth/ConfirmPassword', 'label' => 'Potwierdzenie hasła', 'states' => ['default']],
        'two-factor-challenge' => ['page' => 'auth/TwoFactorChallenge', 'label' => 'Kod uwierzytelniania', 'states' => ['default']],
    ];

    public function show(Request $request): Response
    {
        abort_unless(app()->isLocal(), 404);

        $screen = $request->query('screen');

        if ($screen === null) {
            return Inertia::render('dev/AuthKitchenSink', [
                'screens' => collect(self::SCREENS)
                    ->map(fn (array $definition, string $key): array => [
                        'key' => $key,
                        'label' => $definition['label'],
                        'states' => $definition['states'],
                    ])
                    ->values()
                    ->all(),
            ]);
        }

        $state = $request->query('state', 'default');

        abort_unless(is_string($screen) && is_string($state) && isset(self::SCREENS[$screen]), 404);
        abort_unless(in_array($state, self::SCREENS[$screen]['states'], true), 404);

        return Inertia::render(self::SCREENS[$screen]['page'], $this->props($screen, $state));
    }

    /**
     * Fixture props of one screen in one state. There is no error state: the Inertia Form fills its errors only after a
     * rejected submit, so the error screenshots send the real form (see the change screenshots).
     *
     * @return array<string, mixed>
     */
    private function props(string $screen, string $state): array
    {
        $passwordRules = Password::defaults()->toPasswordRulesString();

        return match ($screen) {
            'login' => $state === 'status'
                ? ['canResetPassword' => true, 'status' => 'Hasło zostało zmienione. Zaloguj się nowym hasłem.']
                : ['canResetPassword' => true],
            'register' => ['passwordRules' => $passwordRules],
            'forgot-password' => $state === 'status'
                ? ['status' => 'Wysłaliśmy link do zresetowania hasła na podany adres e-mail.']
                : [],
            'reset-password' => [
                'token' => 'kitchen-sink-token',
                'email' => $state === 'no-email' ? null : 'jan@example.com',
                'passwordRules' => $passwordRules,
            ],
            'verify-email' => $state === 'status' ? ['status' => 'verification-link-sent'] : [],
            default => [],
        };
    }
}
