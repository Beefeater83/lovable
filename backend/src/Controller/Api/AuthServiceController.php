<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Services\TokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;
use App\Services\CookieService;

class AuthServiceController extends AbstractController
{
    public function __construct(
        private TokenService $authService,
        private CookieService $cookieService,
    ) {
    }

    #[Route('/refresh', name: 'api_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $refreshTokenValue = $request->cookies->get('refresh_token');
        $result = $this->authService->refresh($refreshTokenValue);

        $status = $result['success'] ? 200 : 401;
        $response = $this->json($result['data'], $status);

        if (
            array_key_exists('accessToken', $result)
            && is_string($result['accessToken'])
            && trim($result['accessToken']) !== ''
        ) {
            $response->headers->setCookie(
                $this->cookieService->createAccessCookie($result['accessToken'])
            );
        }

        return $response;
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $refreshTokenValue = $request->cookies->get('refresh_token');
        $this->authService->removeToken($refreshTokenValue);

        $response = $this->json(['success' => true]);
        $response->headers->clearCookie('access_token', '/');
        $response->headers->clearCookie('refresh_token', '/');

        return $response;
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function authStatus(): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json([
                'authenticated' => false,
            ]);
        }

        $user = $this->getUser();

        return $this->json([
            'authenticated' => true,
            'name' => $user->getName()
        ]);
    }
}
