<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Services\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    public function testCreateRefreshToken(): void
    {
        $repository = $this->createMock(RefreshTokenRepository::class);
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager
            ->expects($this->once())
            ->method('persist');

        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new TokenService(
            $repository,
            $jwtManager,
            $entityManager
        );

        $user = new User();

        $token = $service->createRefreshToken($user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }
}
