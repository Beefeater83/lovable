<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Psr\Log\LoggerInterface;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager,
        private string $frontendUrl,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($client, $accessToken) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();
                $user = $this->userRepository->findOneBy(['email' => $email]);

                if (!$user) {
                    throw new AuthenticationException('User not found');
                }

                return $user;
            })
        );
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $this->logger->info('User logged in via Google', [
            'email' => $user->getEmail()
        ]);

        $accessToken = $this->jwtManager->create($user);
        $refreshTokenValue = bin2hex(random_bytes(32));
        $refresh = new RefreshToken();
        $refresh->setToken($refreshTokenValue);
        $refresh->setUser($user);
        $refresh->setExpiresAt(new \DateTimeImmutable('+1 hours'));
        $this->entityManager->persist($refresh);
        $this->entityManager->flush();

        $response = new RedirectResponse($this->frontendUrl . '?login=success');

        $response->headers->setCookie(
            Cookie::create('access_token', $accessToken, new \DateTime('+5 minutes'))
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withPath('/')
        );

        $response->headers->setCookie(
            Cookie::create('refresh_token', $refreshTokenValue, new \DateTime('+1 hours'))
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withPath('/')
        );

        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Google login failed', [
            'reason' => $exception->getMessage(),
        ]);

        return new RedirectResponse(
            $this->frontendUrl . '?login=failed'
        );
    }
}
