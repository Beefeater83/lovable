<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HttpFoundation\Cookie;

class CookieService
{
    public function createAccessCookie(string $accessToken): Cookie
    {
        return Cookie::create('access_token', $accessToken, new \DateTime('+5 minutes'))
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withPath('/');
    }

    public function createRefreshCookie(string $refreshTokenValue): Cookie
    {
        return Cookie::create('refresh_token', $refreshTokenValue, new \DateTime('+1 hours'))
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withPath('/');
    }
}
