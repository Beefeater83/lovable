<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\Api\PasskeyController;
use App\Entity\Passkey;
use App\Entity\User;
use App\Exception\DuplicatePasskeyException;
use App\Exception\PasskeyNotFoundException;
use App\Services\CookieService;
use App\Services\PasskeyService;
use App\Services\TokenService;
use Beefeater\CrudEventBundle\Exception\ResourceNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyControllerTest extends TestCase
{
    private PasskeyService $passkeyService;
    private TokenService $tokenService;
    private CookieService $cookieService;
    private EventDispatcherInterface $eventDispatcher;
    private PasskeyController $controller;

    protected function setUp(): void
    {
        $this->passkeyService = $this->createMock(PasskeyService::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->cookieService = $this->createMock(CookieService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->controller = new PasskeyController(
            $this->passkeyService,
            $this->tokenService,
            $this->cookieService,
            $this->eventDispatcher,
            new NullLogger()
        );

        $this->controller->setContainer($this->createContainerMock());
    }

    private function createContainerMock(?User $user = null): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);

        $tokenStorage = null;
        if ($user !== null) {
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);

            $tokenStorage = $this->createMock(TokenStorageInterface::class);
            $tokenStorage->method('getToken')->willReturn($token);
        }

        $container->method('has')->willReturnCallback(function (string $id) use ($tokenStorage): bool {
            return $id === 'security.token_storage' && $tokenStorage !== null;
        });

        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage): mixed {
            if ($id === 'security.token_storage' && $tokenStorage !== null) {
                return $tokenStorage;
            }

            return null;
        });

        return $container;
    }

    private function authenticateUser(User $user): void
    {
        $this->controller->setContainer($this->createContainerMock($user));
    }

    public function testRegistrationOptionsAuthenticated(): void
    {
        $user = new User();
        $this->authenticateUser($user);

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test RP', 'localhost'),
            PublicKeyCredentialUserEntity::create('user@test.com', '1', 'User'),
            'challenge-bytes'
        );

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($options, 'json')
            ->willReturn('{"challenge":"..."}');

        $this->passkeyService
            ->expects($this->once())
            ->method('createRegistrationOptions')
            ->with($user)
            ->willReturn($options);

        $this->passkeyService
            ->expects($this->once())
            ->method('getSerializer')
            ->willReturn($serializer);

        $response = $this->controller->registrationOptions();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"challenge":"..."}', $response->getContent());
    }

    public function testRegistrationVerifyDuplicatePasskey(): void
    {
        $user = new User();
        $this->authenticateUser($user);

        $request = new Request([], [], [], [], [], [], json_encode([
            'id' => 'existing-id',
            'rawId' => 'existing-id',
            'type' => 'public-key',
        ]));

        $this->passkeyService
            ->expects($this->once())
            ->method('verifyRegistration')
            ->willThrowException(new DuplicatePasskeyException(
                'Passkey with this credential ID is already registered'
            ));

        $response = $this->controller->registrationVerify($request);
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testRegistrationVerifySuccess(): void
    {
        $user = new User();
        $this->authenticateUser($user);

        $request = new Request([], [], [], [], [], [], json_encode([
            'id' => 'new-id',
            'rawId' => 'new-id',
            'type' => 'public-key',
            'name' => 'My Phone',
        ]));

        $passkey = new Passkey();
        $passkey->setName('My Phone');
        $ref = new \ReflectionProperty(Passkey::class, 'id');
        $ref->setValue($passkey, 123);

        $this->passkeyService
            ->expects($this->once())
            ->method('verifyRegistration')
            ->with($user, $this->anything(), 'My Phone', $this->anything())
            ->willReturn($passkey);

        $response = $this->controller->registrationVerify($request);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(123, $data['passkey']['id']);
        $this->assertSame('My Phone', $data['passkey']['name']);
    }

    public function testAuthenticationOptions(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['email' => 'user@example.com']));

        $options = PublicKeyCredentialRequestOptions::create('challenge-bytes', 'localhost');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($options, 'json')
            ->willReturn('{"challenge":"..."}');

        $this->passkeyService
            ->expects($this->once())
            ->method('createAuthenticationOptions')
            ->with('user@example.com')
            ->willReturn($options);

        $this->passkeyService
            ->expects($this->once())
            ->method('getSerializer')
            ->willReturn($serializer);

        $response = $this->controller->authenticationOptions($request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAuthenticationVerifySuccessSetsCookies(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'id' => 'auth-id',
            'rawId' => 'auth-id',
            'type' => 'public-key',
        ]));

        $user = new User();
        $user->setEmail('user@test.com');
        $user->setName('Alice');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 77);

        $this->passkeyService
            ->expects($this->once())
            ->method('verifyAuthentication')
            ->willReturn($user);

        $this->tokenService
            ->expects($this->once())
            ->method('createAccessToken')
            ->with($user)
            ->willReturn('jwt-access-token');

        $this->tokenService
            ->expects($this->once())
            ->method('createRefreshToken')
            ->with($user)
            ->willReturn('refresh-token-val');

        $this->cookieService
            ->expects($this->once())
            ->method('createAccessCookie')
            ->with('jwt-access-token')
            ->willReturn(Cookie::create('access_token', 'jwt-access-token'));

        $this->cookieService
            ->expects($this->once())
            ->method('createRefreshCookie')
            ->with('refresh-token-val')
            ->willReturn(Cookie::create('refresh_token', 'refresh-token-val'));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $response = $this->controller->authenticationVerify($request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $cookies = $response->headers->getCookies();
        $this->assertCount(2, $cookies);
        $this->assertSame('access_token', $cookies[0]->getName());
        $this->assertSame('refresh_token', $cookies[1]->getName());
    }

    public function testAuthenticationVerifyPasskeyNotFound(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'id' => 'unknown-id',
            'rawId' => 'unknown-id',
            'type' => 'public-key',
        ]));

        $this->passkeyService
            ->expects($this->once())
            ->method('verifyAuthentication')
            ->willThrowException(new PasskeyNotFoundException('Passkey not found'));

        $response = $this->controller->authenticationVerify($request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testDeletePasskeyOwnershipDenied(): void
    {
        $user = new User();
        $this->authenticateUser($user);

        $this->passkeyService
            ->expects($this->once())
            ->method('deletePasskey')
            ->with($user, 99)
            ->willThrowException(new AccessDeniedException('Access denied'));

        $response = $this->controller->delete(99);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testDeletePasskeyNotFound(): void
    {
        $user = new User();
        $this->authenticateUser($user);

        $this->passkeyService
            ->expects($this->once())
            ->method('deletePasskey')
            ->with($user, 99)
            ->willThrowException(new ResourceNotFoundException(Passkey::class, '99'));

        $response = $this->controller->delete(99);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeletePasskeySuccess(): void
    {
        $user = new User();
        $this->authenticateUser($user);

        $this->passkeyService
            ->expects($this->once())
            ->method('deletePasskey')
            ->with($user, 42);

        $response = $this->controller->delete(42);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['success']);
    }
}
