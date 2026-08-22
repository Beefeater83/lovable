<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Event\UserLoggedInEvent;
use App\Exception\DuplicatePasskeyException;
use App\Exception\PasskeyNotFoundException;
use App\Exception\PasskeyValidationException;
use App\Services\CookieService;
use App\Services\PasskeyService;
use App\Services\TokenService;
use Beefeater\CrudEventBundle\Exception\ResourceNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PasskeyController extends AbstractController
{
    public function __construct(
        private readonly PasskeyService $passkeyService,
        private readonly TokenService $tokenService,
        private readonly CookieService $cookieService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/iam/passkey/registration/options', name: 'api_passkey_registration_options', methods: ['POST'])]
    public function registrationOptions(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $options = $this->passkeyService->createRegistrationOptions($user);
            $json = $this->passkeyService->getSerializer()->serialize($options, 'json');

            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate passkey registration options', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => 'Failed to generate registration options'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/iam/passkey/registration/verify', name: 'api_passkey_registration_verify', methods: ['POST'])]
    public function registrationVerify(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray();
            $credentialData = $payload['credential'] ?? $payload;
            $name = isset($payload['name']) && is_string($payload['name']) ? $payload['name'] : null;

            $userAgent = $request->headers->get('User-Agent', '');

            $passkey = $this->passkeyService->verifyRegistration(
                $user,
                $credentialData,
                $name,
                $request->getHost(),
                $userAgent
            );

            return $this->json([
                'success' => true,
                'passkey' => [
                    'id' => $passkey->getId(),
                    'name' => $passkey->getName(),
                    'createdAt' => $passkey->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
            ], Response::HTTP_CREATED);
        } catch (DuplicatePasskeyException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (PasskeyValidationException $e) {
            $this->logger->warning('Passkey registration validation failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during passkey registration', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => 'Registration verification failed'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/iam/passkey/authentication/options', name: 'api_passkey_authentication_options', methods: ['POST'])]
    public function authenticationOptions(Request $request): JsonResponse
    {
        try {
            $content = $request->getContent();
            $payload = ($content !== '' && $content !== '0') ? json_decode($content, true) : [];
            $email = isset($payload['email']) && is_string($payload['email']) ? $payload['email'] : null;

            $options = $this->passkeyService->createAuthenticationOptions($email);
            $json = $this->passkeyService->getSerializer()->serialize($options, 'json');

            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate passkey authentication options', [
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => 'Failed to generate authentication options'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/iam/passkey/authentication/verify', name: 'api_passkey_authentication_verify', methods: ['POST'])]
    public function authenticationVerify(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $credentialData = $payload['credential'] ?? $payload;

            $user = $this->passkeyService->verifyAuthentication(
                $credentialData,
                $request->getHost()
            );

            $accessToken = $this->tokenService->createAccessToken($user);
            $refreshTokenValue = $this->tokenService->createRefreshToken($user);

            $this->eventDispatcher->dispatch(new UserLoggedInEvent($user));

            $response = new JsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                ],
            ]);

            $response->headers->setCookie(
                $this->cookieService->createAccessCookie($accessToken)
            );
            $response->headers->setCookie(
                $this->cookieService->createRefreshCookie($refreshTokenValue)
            );

            return $response;
        } catch (PasskeyNotFoundException $e) {
            return $this->json(['error' => 'Passkey not found'], Response::HTTP_UNAUTHORIZED);
        } catch (PasskeyValidationException $e) {
            $this->logger->warning('Passkey authentication validation failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during passkey authentication', [
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => 'Authentication verification failed'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/iam/passkeys', name: 'api_passkey_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $passkeys = $this->passkeyService->listPasskeys($user);

        return $this->json($passkeys);
    }

    #[Route('/iam/passkeys/{id}', name: 'api_passkey_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->passkeyService->deletePasskey($user, $id);

            return $this->json(['success' => true]);
        } catch (ResourceNotFoundException $e) {
            return $this->json(['error' => 'Passkey not found'], Response::HTTP_NOT_FOUND);
        } catch (AccessDeniedException $e) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete passkey', [
                'user_id' => $user->getId(),
                'passkey_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => 'Failed to delete passkey'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
