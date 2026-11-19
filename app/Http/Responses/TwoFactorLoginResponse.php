<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\LeavesInertiaForOAuthAuthorization;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\TwoFactorLoginResponse as FortifyTwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse extends FortifyTwoFactorLoginResponse
{
    use LeavesInertiaForOAuthAuthorization;

    /**
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        return $this->oauthAuthorizationLocation($request) ?? parent::toResponse($request);
    }
}
