<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Event\UserLoggedInEvent;
use App\Repository\UserRepository;
use App\Services\OtpService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OtpAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager,
        private string $frontendUrl,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private OtpService $otpService
    ) {
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $data = $request->toArray();
        $otp = $data['otp'] ?? false;
        $email = $data['email'] ?? false;

        if (!$otp || !$email) {
            throw new AuthenticationException('email or otp missing');
        }

        $otpSuccess = $this->otpService->verificationOtp($email, $otp);

        if (!$otpSuccess) {
            throw new AuthenticationException('otp is invalid');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email) {

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
        return $request->attributes->get('_route') === 'verification_otp';
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $this->logger->info('User logged in via OTP', [
            'email' => $user->getEmail(),
        ]);

        $accessToken = $this->jwtManager->create($user);
        $refreshTokenValue = bin2hex(random_bytes(32));
        $refresh = new RefreshToken();
        $refresh->setToken($refreshTokenValue);
        $refresh->setUser($user);
        $refresh->setExpiresAt(new \DateTimeImmutable('+1 hours'));
        $this->entityManager->persist($refresh);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new UserLoggedInEvent($user));

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
        $this->logger->warning('OTP login failed', [
            'reason' => $exception->getMessage(),
        ]);

        return new RedirectResponse(
            $this->frontendUrl . '?login=failed'
        );
    }
}
