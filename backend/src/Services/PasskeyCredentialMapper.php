<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Passkey;
use App\Entity\User;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\CertificateTrustPath;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\Util\Base64;

class PasskeyCredentialMapper
{
    public function fromCredentialRecord(
        CredentialRecord $credentialRecord,
        User $user,
        string $name
    ): Passkey {
        $passkey = new Passkey();
        $passkey->setUser($user);
        $passkey->setName($name);
        $passkey->setCredentialId(Base64UrlSafe::encodeUnpadded($credentialRecord->publicKeyCredentialId));
        $passkey->setPublicKey(Base64UrlSafe::encodeUnpadded($credentialRecord->credentialPublicKey));
        $passkey->setUserHandle($credentialRecord->userHandle);
        $passkey->setCounter($credentialRecord->counter);
        $passkey->setTransports($credentialRecord->transports);
        $passkey->setAaguid($credentialRecord->aaguid->toRfc4122());
        $passkey->setAttestationType($credentialRecord->attestationType);

        $trustPath = $credentialRecord->trustPath;
        $trustPathData = match (true) {
            $trustPath instanceof CertificateTrustPath => ['x5c' => $trustPath->certificates],
            default => [],
        };
        $passkey->setTrustPath($trustPathData);

        $passkey->setBackupEligible($credentialRecord->backupEligible);
        $passkey->setBackupStatus($credentialRecord->backupStatus);
        $passkey->setUvInitialized($credentialRecord->uvInitialized);

        return $passkey;
    }

    public function toCredentialRecord(Passkey $passkey): CredentialRecord
    {
        $trustPathData = $passkey->getTrustPath();
        $trustPath = match (true) {
            isset($trustPathData['x5c'])
            && is_array($trustPathData['x5c']) => CertificateTrustPath::create($trustPathData['x5c']),
            default => EmptyTrustPath::create(),
        };

        return CredentialRecord::create(
            publicKeyCredentialId: Base64::decode($passkey->getCredentialId()),
            type: 'public-key',
            transports: $passkey->getTransports(),
            attestationType: $passkey->getAttestationType(),
            trustPath: $trustPath,
            aaguid: Uuid::fromString($passkey->getAaguid()),
            credentialPublicKey: Base64::decode($passkey->getPublicKey()),
            userHandle: $passkey->getUserHandle(),
            counter: $passkey->getCounter(),
            otherUI: null,
            backupEligible: $passkey->getBackupEligible(),
            backupStatus: $passkey->getBackupStatus(),
            uvInitialized: $passkey->getUvInitialized(),
        );
    }
}
