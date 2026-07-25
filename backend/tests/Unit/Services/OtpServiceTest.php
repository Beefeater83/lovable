<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Repository\UserRepository;
use App\Services\OtpService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class OtpServiceTest extends TestCase
{
    public function testSendOtpReturnsFalseWhenUserNotFound(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $twig = $this->createMock(Environment::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);

        $userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@gmail.com'])
            ->willReturn(null);

        $mailer
            ->expects($this->never())
            ->method('send');

        $service = new OtpService(
            $userRepository,
            $twig,
            $mailer,
            $logger,
            $cache
        );

        $this->assertFalse(
            $service->sendOtp('test@gmail.com')
        );
    }
}
