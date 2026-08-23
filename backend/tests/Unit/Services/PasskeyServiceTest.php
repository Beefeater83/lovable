<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Passkey;
use App\Entity\User;
use App\Exception\DuplicatePasskeyException;
use App\Exception\PasskeyNotFoundException;
use App\Exception\PasskeyValidationException;
use App\Repository\PasskeyRepository;
use App\Repository\UserRepository;
use App\Services\PasskeyCredentialMapper;
use App\Services\PasskeyService;
use Beefeater\CrudEventBundle\Exception\ResourceNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PasskeyServiceTest extends TestCase
{
    private PasskeyRepository $passkeyRepository;
    private PasskeyCredentialMapper $credentialMapper;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private CacheItemPoolInterface $cache;
    private PasskeyService $service;

    protected function setUp(): void
    {
        $this->passkeyRepository = $this->createMock(PasskeyRepository::class);
        $this->credentialMapper = new PasskeyCredentialMapper();
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);

        $this->service = new PasskeyService(
            $this->passkeyRepository,
            $this->credentialMapper,
            $this->userRepository,
            $this->entityManager,
            $this->cache,
            new NullLogger(),
            'localhost',
            'Security',
            ['http://localhost', 'http://localhost:3000']
        );
    }

    public function testCreateRegistrationOptions(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 42);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set');
        $cacheItem->expects($this->once())->method('expiresAfter')->with(120);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->with('passkey_reg_options_user_42')
            ->willReturn($cacheItem);

        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $this->passkeyRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn([]);

        $options = $this->service->createRegistrationOptions($user);

        $this->assertSame('localhost', $options->rp->id);
        $this->assertSame('Security', $options->rp->name);
        $this->assertSame('alice@example.com', $options->user->name);
        $this->assertSame('42', $options->user->id);
        $this->assertNotEmpty($options->challenge);
        $this->assertCount(6, $options->pubKeyCredParams);
        $this->assertEmpty($options->excludeCredentials);
    }

    public function testCreateAuthenticationOptionsWithEmail(): void
    {
        $user = new User();
        $user->setName('Bob');
        $user->setEmail('bob@example.com');

        $passkey = new Passkey();
        $passkey->setCredentialId(Base64UrlSafe::encodeUnpadded('test-credential-id'));
        $passkey->setPublicKey(Base64UrlSafe::encodeUnpadded('test-public-key'));
        $passkey->setUserHandle('10');
        $passkey->setAaguid('00000000-0000-0000-0000-000000000000');
        $passkey->setTransports(['internal']);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'bob@example.com'])
            ->willReturn($user);

        $this->passkeyRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn([$passkey]);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set');
        $cacheItem->expects($this->once())->method('expiresAfter')->with(120);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $options = $this->service->createAuthenticationOptions('bob@example.com');

        $this->assertSame('localhost', $options->rpId);
        $this->assertNotEmpty($options->challenge);
        $this->assertCount(1, $options->allowCredentials);
    }

    public function testVerifyRegistrationExpiredChallengeThrowsException(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHit')->willReturn(false);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->with('passkey_reg_options_user_1')
            ->willReturn($cacheItem);

        $this->expectException(PasskeyValidationException::class);
        $this->expectExceptionMessage('Registration challenge expired or invalid');

        $this->service->verifyRegistration(
            $user,
            '{}',
            'MacBook',
            'localhost',
            'Mozilla/5.0'
        );
    }

    public function testListPasskeys(): void
    {
        $user = new User();
        $passkey = new Passkey();
        $passkey->setName('Touch ID');
        $passkey->setAaguid('00000000-0000-0000-0000-000000000000');
        $passkey->setTransports(['internal']);
        $passkey->setBackupEligible(true);
        $passkey->setBackupStatus(true);

        $ref = new \ReflectionProperty(Passkey::class, 'id');
        $ref->setValue($passkey, 5);

        $this->passkeyRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn([$passkey]);

        $result = $this->service->listPasskeys($user);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['id']);
        $this->assertSame('Touch ID', $result[0]['name']);
        $this->assertTrue($result[0]['backupEligible']);
        $this->assertArrayNotHasKey('publicKey', $result[0]);
        $this->assertArrayNotHasKey('trustPath', $result[0]);
    }

    public function testDeletePasskeyOwnershipCheck(): void
    {
        $user1 = new User();
        $ref1 = new \ReflectionProperty(User::class, 'id');
        $ref1->setValue($user1, 1);

        $user2 = new User();
        $ref2 = new \ReflectionProperty(User::class, 'id');
        $ref2->setValue($user2, 2);

        $passkey = new Passkey();
        $passkey->setUser($user2);

        $this->passkeyRepository
            ->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($passkey);

        $this->expectException(AccessDeniedException::class);
        $this->service->deletePasskey($user1, 10);
    }

    public function testDeletePasskeySuccess(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $passkey = new Passkey();
        $passkey->setUser($user);

        $this->passkeyRepository
            ->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($passkey);

        $this->entityManager->expects($this->once())->method('remove')->with($passkey);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->deletePasskey($user, 10);
    }
}
