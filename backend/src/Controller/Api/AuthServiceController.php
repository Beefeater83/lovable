<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RefreshTokenRepository;
use App\Services\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;

class AuthServiceController extends CrudController
{
    private RefreshTokenRepository $refreshTokenRepository;
    private TokenService $authService;

    public function __construct(
        EntityManagerInterface $entityManager,
        RefreshTokenRepository $refreshTokenRepository,
        TokenService $authService
    ) {
        parent::__construct($refreshTokenRepository, $entityManager);
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->authService = $authService;
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
                Cookie::create('access_token', $result['accessToken'], new \DateTimeImmutable('+5 minutes'))
                    ->withHttpOnly(true)
                    ->withSecure(true)
                    ->withPath('/')
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
}
