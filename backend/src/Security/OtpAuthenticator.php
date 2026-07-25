<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Event\UserLoggedInEvent;
use App\Repository\UserRepository;
use App\Services\CookieService;
use App\Services\OtpService;
use App\Services\TokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
        private OtpService $otpService,
        private TokenService $tokenService,
        private CookieService $cookieService,
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

        $accessToken = $this->tokenService->createAccessToken($user);
        $refreshTokenValue = $this->tokenService->createRefreshToken($user);

        $this->eventDispatcher->dispatch(new UserLoggedInEvent($user));

        $response = new JsonResponse([
            'success' => true,
        ]);

        $response->headers->setCookie(
            $this->cookieService->createAccessCookie($accessToken)
        );

        $response->headers->setCookie(
            $this->cookieService->createRefreshCookie($refreshTokenValue)
        );

        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('OTP login failed', [
            'reason' => $exception->getMessage(),
        ]);

        return new JsonResponse([
            'success' => false,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
