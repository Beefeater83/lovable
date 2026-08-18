<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Passkey;
use App\Entity\User;
use App\Exception\DuplicatePasskeyException;
use App\Exception\PasskeyNotFoundException;
use App\Exception\PasskeyValidationException;
use App\Repository\PasskeyRepository;
use App\Repository\UserRepository;
use Beefeater\CrudEventBundle\Exception\ResourceNotFoundException;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\Ed25519;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyService
{
    private ?SerializerInterface $serializer = null;

    /**
     * @param string|string[] $allowedOrigins
     */
    public function __construct(
        private readonly PasskeyRepository $passkeyRepository,
        private readonly PasskeyCredentialMapper $credentialMapper,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $rpId,
        private readonly string $rpName,
        private readonly string|array $allowedOrigins,
    ) {
    }

    public function createRegistrationOptions(User $user): PublicKeyCredentialCreationOptions
    {
        $challenge = random_bytes(32);
        $rp = PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId);
        $userHandle = (string) $user->getId();
        $userEntity = PublicKeyCredentialUserEntity::create($user->getEmail(), $userHandle, $user->getEmail());

        $pubKeyCredParams = [
            PublicKeyCredentialParameters::createPk(-7),   // ES256
            PublicKeyCredentialParameters::createPk(-257), // RS256
            PublicKeyCredentialParameters::createPk(-37),  // PS256
            PublicKeyCredentialParameters::createPk(-35),  // ES384
            PublicKeyCredentialParameters::createPk(-36),  // ES512
            PublicKeyCredentialParameters::createPk(-8),   // Ed25519
        ];

        $existingPasskeys = $this->passkeyRepository->findByUser($user);
        $excludeCredentials = [];
        foreach ($existingPasskeys as $existingPasskey) {
            $excludeCredentials[] = $this->credentialMapper
                ->toCredentialRecord($existingPasskey)
                ->getPublicKeyCredentialDescriptor();
        }

        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            authenticatorAttachment: null,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rp,
            user: $userEntity,
            challenge: $challenge,
            pubKeyCredParams: $pubKeyCredParams,
            authenticatorSelection: $authenticatorSelection,
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $excludeCredentials,
            timeout: 60000,
        );

        $cacheKey = 'passkey_reg_options_user_' . $user->getId();
        $item = $this->cache->getItem($cacheKey);
        $item->set(serialize($options));
        $item->expiresAfter(120);
        $this->cache->save($item);

        return $options;
    }

    /**
     * @param array<string, mixed>|string $credentialData
     */
    public function verifyRegistration(
        User $user,
        array|string $credentialData,
        ?string $customName,
        string $host
    ): Passkey {
        $cacheKey = 'passkey_reg_options_user_' . $user->getId();
        $item = $this->cache->getItem($cacheKey);
        if (!$item->isHit()) {
            throw new PasskeyValidationException('Registration challenge expired or invalid');
        }

        /** @var PublicKeyCredentialCreationOptions $creationOptions */
        $creationOptions = unserialize($item->get());
        $this->cache->deleteItem($cacheKey);

        $credentialJson = is_array($credentialData) ? json_encode($credentialData) : $credentialData;
        if ($credentialJson === false || trim($credentialJson) === '') {
            throw new PasskeyValidationException('Invalid credential JSON payload');
        }

        try {
            /** @var PublicKeyCredential $publicKeyCredential */
            $publicKeyCredential = $this->getSerializer()->deserialize(
                $credentialJson,
                PublicKeyCredential::class,
                'json'
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to deserialize PublicKeyCredential for registration', [
                'exception' => $e->getMessage(),
            ]);
            throw new PasskeyValidationException('Invalid credential data format: ' . $e->getMessage(), 0, $e);
        }

        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new PasskeyValidationException('Expected AuthenticatorAttestationResponse');
        }

        $credentialId = Base64UrlSafe::encodeUnpadded($publicKeyCredential->rawId);
        $existing = $this->passkeyRepository->findOneBy(['credentialId' => $credentialId]);
        if ($existing !== null) {
            throw new DuplicatePasskeyException('Passkey with this credential ID is already registered');
        }

        $validator = $this->getAttestationValidator();
        try {
            $credentialRecord = $validator->check(
                $publicKeyCredential->response,
                $creationOptions,
                $host
            );
        } catch (\Throwable $e) {
            $this->logger->error('WebAuthn attestation verification failed', [
                'exception' => $e->getMessage(),
            ]);
            throw new PasskeyValidationException('WebAuthn registration verification failed: '
                . $e->getMessage(), 0, $e);
        }

        $name = (!empty($customName) && trim($customName) !== '')
            ? trim($customName)
            : 'Passkey ' . (new \DateTimeImmutable())->format('Y-m-d H:i');

        $passkey = $this->credentialMapper->fromCredentialRecord($credentialRecord, $user, $name);
        $this->entityManager->persist($passkey);
        $this->entityManager->flush();

        return $passkey;
    }

    public function createAuthenticationOptions(?string $email = null): PublicKeyCredentialRequestOptions
    {
        $challenge = random_bytes(32);
        $allowCredentials = [];

        if ($email !== null && trim($email) !== '') {
            $user = $this->userRepository->findOneBy(['email' => trim($email)]);
            if ($user !== null) {
                $passkeys = $this->passkeyRepository->findByUser($user);
                foreach ($passkeys as $p) {
                    $allowCredentials[] = $this->credentialMapper
                        ->toCredentialRecord($p)
                        ->getPublicKeyCredentialDescriptor();
                }
            }
        }

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId,
            allowCredentials: $allowCredentials,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60000,
        );

        $challengeBase64Url = Base64UrlSafe::encodeUnpadded($challenge);
        $cacheKey = 'passkey_auth_options_' . $challengeBase64Url;
        $item = $this->cache->getItem($cacheKey);
        $item->set(serialize($options));
        $item->expiresAfter(120);
        $this->cache->save($item);

        return $options;
    }

    /**
     * @param array<string, mixed>|string $credentialData
     */
    public function verifyAuthentication(array|string $credentialData, string $host): User
    {
        $credentialJson = is_array($credentialData) ? json_encode($credentialData) : $credentialData;
        if ($credentialJson === false || trim($credentialJson) === '') {
            throw new PasskeyValidationException('Invalid credential JSON payload');
        }

        try {
            /** @var PublicKeyCredential $publicKeyCredential */
            $publicKeyCredential = $this->getSerializer()->deserialize(
                $credentialJson,
                PublicKeyCredential::class,
                'json'
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to deserialize PublicKeyCredential for authentication', [
                'exception' => $e->getMessage(),
            ]);
            throw new PasskeyValidationException('Invalid credential data format: ' . $e->getMessage(), 0, $e);
        }

        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            throw new PasskeyValidationException('Expected AuthenticatorAssertionResponse');
        }

        $credentialId = Base64UrlSafe::encodeUnpadded($publicKeyCredential->rawId);
        try {
            $passkey = $this->passkeyRepository->findOneByCredentialId($credentialId);
        } catch (ResourceNotFoundException $e) {
            throw new PasskeyNotFoundException('Passkey not found for provided credential ID', 0, $e);
        }

        $clientChallenge = $publicKeyCredential->response->clientDataJSON->challenge;
        $challengeBase64Url = Base64UrlSafe::encodeUnpadded($clientChallenge);
        $cacheKey = 'passkey_auth_options_' . $challengeBase64Url;
        $item = $this->cache->getItem($cacheKey);
        if (!$item->isHit()) {
            throw new PasskeyValidationException('Authentication challenge expired or invalid');
        }

        /** @var PublicKeyCredentialRequestOptions $requestOptions */
        $requestOptions = unserialize($item->get());
        $this->cache->deleteItem($cacheKey);

        $credentialRecord = $this->credentialMapper->toCredentialRecord($passkey);
        $validator = $this->getAssertionValidator();
        $userHandle = $passkey->getUserHandle();

        try {
            $updatedRecord = $validator->check(
                $credentialRecord,
                $publicKeyCredential->response,
                $requestOptions,
                $host,
                $userHandle
            );
        } catch (\Throwable $e) {
            $this->logger->error('WebAuthn assertion verification failed', [
                'exception' => $e->getMessage(),
            ]);
            throw new PasskeyValidationException('WebAuthn authentication verification failed: '
                . $e->getMessage(), 0, $e);
        }

        $passkey->setCounter($updatedRecord->counter);
        $passkey->setBackupEligible($updatedRecord->backupEligible);
        $passkey->setBackupStatus($updatedRecord->backupStatus);
        if ($passkey->getUvInitialized() === false || $passkey->getUvInitialized() === null) {
            $passkey->setUvInitialized($updatedRecord->uvInitialized);
        }
        $passkey->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $passkey->getUser();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPasskeys(User $user): array
    {
        $passkeys = $this->passkeyRepository->findByUser($user);
        $result = [];
        foreach ($passkeys as $p) {
            $result[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'aaguid' => $p->getAaguid(),
                'transports' => $p->getTransports(),
                'backupEligible' => $p->getBackupEligible(),
                'backupStatus' => $p->getBackupStatus(),
                'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'lastUsedAt' => $p->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $result;
    }

    public function deletePasskey(User $user, int $id): void
    {
        $passkey = $this->passkeyRepository->find($id);
        if ($passkey->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException('You do not have permission to delete this passkey');
        }

        $this->entityManager->remove($passkey);
        $this->entityManager->flush();
    }

    public function getSerializer(): SerializerInterface
    {
        if ($this->serializer === null) {
            $factory = new WebauthnSerializerFactory($this->getAttestationStatementSupportManager());
            $this->serializer = $factory->create();
        }

        return $this->serializer;
    }

    private function getAttestationValidator(): AuthenticatorAttestationResponseValidator
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($this->getAllowedOrigins(), allowSubdomains: true);
        $factory->setAlgorithmManager($this->getAlgorithmManager());
        $factory->setAttestationStatementSupportManager($this->getAttestationStatementSupportManager());

        return AuthenticatorAttestationResponseValidator::create($factory->creationCeremony());
    }

    private function getAssertionValidator(): AuthenticatorAssertionResponseValidator
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($this->getAllowedOrigins(), allowSubdomains: true);
        $factory->setAlgorithmManager($this->getAlgorithmManager());

        return AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());
    }

    /**
     * @return string[]
     */
    private function getAllowedOrigins(): array
    {
        if (is_array($this->allowedOrigins)) {
            $origins = $this->allowedOrigins;
        } else {
            $origins = array_filter(array_map('trim', explode(',', (string) $this->allowedOrigins)));
        }

        if (empty($origins)) {
            $origins = ['http://localhost', 'http://127.0.0.1', 'https://' . $this->rpId];
        }

        return array_values($origins);
    }

    private function getAlgorithmManager(): Manager
    {
        return Manager::create()->add(
            ES256::create(),
            ES384::create(),
            ES512::create(),
            RS256::create(),
            RS384::create(),
            RS512::create(),
            PS256::create(),
            PS384::create(),
            PS512::create(),
            Ed25519::create()
        );
    }

    private function getAttestationStatementSupportManager(): AttestationStatementSupportManager
    {
        return new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);
    }
}
