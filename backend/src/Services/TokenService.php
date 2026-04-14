<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class TokenService
{
    private RefreshTokenRepository $refreshTokenRepository;
    private JWTTokenManagerInterface $jwtManager;
    public function __construct(
        RefreshTokenRepository $refreshTokenRepository,
        JWTTokenManagerInterface $jwtManager
    ) {
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->jwtManager = $jwtManager;
    }

    public function refresh(?string $tokenValue): array
    {
        if (!$tokenValue) {
            return [
                'success' => false,
                'data' => ['error' => 'Refresh token missing']
            ];
        }

        $refreshToken = $this->refreshTokenRepository->findOneBy([
            'token' => $tokenValue
        ]);

        if (!$refreshToken) {
            return [
                'success' => false,
                'data' => ['error' => 'Refresh token invalid']
            ];
        }

        if ($refreshToken->getExpiresAt() < new \DateTimeImmutable()) {
            $this->refreshTokenRepository->remove($refreshToken);

            return [
                'success' => false,
                'data' => ['error' => 'Refresh token expired']
            ];
        }

        $user = $refreshToken->getUser();
        $newAccessToken = $this->jwtManager->create($user);

        return [
            'success' => true,
            'data' => ['success' => true],
            'accessToken' => $newAccessToken
        ];
    }

    public function removeToken(?string $tokenValue): void
    {
        if (!is_string($tokenValue) || trim($tokenValue) === '') {
            return;
        }

        $refreshToken = $this->refreshTokenRepository->findOneBy([
            'token' => $tokenValue
        ]);

        if (!$refreshToken) {
            return;
        }

        $this->refreshTokenRepository->remove($refreshToken);
    }
}
