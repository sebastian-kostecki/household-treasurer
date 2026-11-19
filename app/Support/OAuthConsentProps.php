<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Passport\Client;

/**
 * Props of the OAuth consent page (`auth/OAuthAuthorize`), built from the parameters Passport passes to its
 * authorization view. The scope list is Polish text; it names recording cash operations only when the write tool is on.
 */
class OAuthConsentProps
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return array{client_name: string, scopes: list<string>, auth_token: string, client_id: string, state: string|null, csrf_token: string}
     */
    public static function fromAuthorization(array $parameters): array
    {
        $client = $parameters['client'];
        $request = $parameters['request'];
        $authToken = $parameters['authToken'];

        assert($client instanceof Client && $request instanceof Request && is_string($authToken));

        $state = $request->input('state');

        return [
            'client_name' => $client->name,
            'scopes' => self::scopeDescriptions(),
            'auth_token' => $authToken,
            'client_id' => (string) $client->getKey(),
            'state' => is_string($state) ? $state : null,
            'csrf_token' => csrf_token(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function scopeDescriptions(): array
    {
        $texts = UiTranslations::all()['auth']['oauth_authorize'] ?? [];

        $scopes = [(string) ($texts['scope_read'] ?? '')];

        if (filter_var(config('services.mcp.write_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $scopes[] = (string) ($texts['scope_write'] ?? '');
        }

        return $scopes;
    }
}
