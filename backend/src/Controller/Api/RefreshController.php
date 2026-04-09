<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RefreshTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;

class RefreshController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $refreshTokenRepository,
        private JWTTokenManagerInterface $jwtManager
    ) {}

    #[Route('/refresh', name: 'api_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $refreshTokenValue = $request->cookies->get('refresh_token');
        if (!$refreshTokenValue) {
            return $this->json(['error' => 'Refresh token missing'], 401);
        }

        $refreshToken = $this->refreshTokenRepository->findOneByToken($refreshTokenValue);

        if (!$refreshToken) {
            return $this->json(['error' => 'Refresh token invalid'], 401);
        }

        if ($refreshToken->getExpiresAt() < new \DateTimeImmutable()) {
            $this->entityManager->remove($refreshToken);
            $this->entityManager->flush();

            return $this->json(['error' => 'Refresh token expired'], 401);
        }

        $user = $refreshToken->getUser();
        $newAccessToken = $this->jwtManager->create($user);

        $response = $this->json(['success' => true]);
        $response->headers->setCookie(
            Cookie::create('access_token', $newAccessToken, new \DateTimeImmutable('+5 minutes'))
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withPath('/')
        );

        return $response;
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $refreshTokenValue = $request->cookies->get('refresh_token');

        if ($refreshTokenValue) {
            $refreshToken = $this->refreshTokenRepository->findOneByToken($refreshTokenValue);
            if ($refreshToken) {
                $this->entityManager->remove($refreshToken);
                $this->entityManager->flush();
            }
        }

        $response = $this->json(['success' => true]);
        $response->headers->clearCookie('access_token', '/');
        $response->headers->clearCookie('refresh_token', '/api/refresh');

        return $response;
    }
}
