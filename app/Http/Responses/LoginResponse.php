<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\LeavesInertiaForOAuthAuthorization;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse extends FortifyLoginResponse
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
