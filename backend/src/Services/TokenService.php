<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class TokenService
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function createAccessToken(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    public function createRefreshToken(User $user): string
    {
        $refreshTokenValue = bin2hex(random_bytes(32));
        $refresh = new RefreshToken();
        $refresh->setToken($refreshTokenValue);
        $refresh->setUser($user);
        $refresh->setExpiresAt(new \DateTimeImmutable('+1 hours'));
        $this->entityManager->persist($refresh);
        $this->entityManager->flush();

        return $refreshTokenValue;
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
