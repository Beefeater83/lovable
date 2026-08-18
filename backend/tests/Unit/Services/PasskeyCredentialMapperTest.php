<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Passkey;
use App\Entity\User;
use App\Services\PasskeyCredentialMapper;
use ParagonIE\ConstantTime\Base64UrlSafe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\CertificateTrustPath;
use Webauthn\TrustPath\EmptyTrustPath;

class PasskeyCredentialMapperTest extends TestCase
{
    private PasskeyCredentialMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PasskeyCredentialMapper();
    }

    public function testFromCredentialRecordWithEmptyTrustPath(): void
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');

        $rawCredentialId = random_bytes(32);
        $rawPublicKey = random_bytes(64);
        $aaguid = Uuid::v4();

        $record = CredentialRecord::create(
            publicKeyCredentialId: $rawCredentialId,
            type: 'public-key',
            transports: ['internal', 'hybrid'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: $rawPublicKey,
            userHandle: '42',
            counter: 5,
            otherUI: null,
            backupEligible: true,
            backupStatus: true,
            uvInitialized: true,
        );

        $passkey = $this->mapper->fromCredentialRecord($record, $user, 'MacBook Touch ID');

        $this->assertSame($user, $passkey->getUser());
        $this->assertSame('MacBook Touch ID', $passkey->getName());
        $this->assertSame(Base64UrlSafe::encodeUnpadded($rawCredentialId), $passkey->getCredentialId());
        $this->assertSame(Base64UrlSafe::encodeUnpadded($rawPublicKey), $passkey->getPublicKey());
        $this->assertSame('42', $passkey->getUserHandle());
        $this->assertSame(5, $passkey->getCounter());
        $this->assertSame(['internal', 'hybrid'], $passkey->getTransports());
        $this->assertSame('none', $passkey->getAttestationType());
        $this->assertSame([], $passkey->getTrustPath());
        $this->assertSame($aaguid->toRfc4122(), $passkey->getAaguid());
        $this->assertTrue($passkey->getBackupEligible());
        $this->assertTrue($passkey->getBackupStatus());
        $this->assertTrue($passkey->getUvInitialized());
    }

    public function testToCredentialRecordAndRoundTrip(): void
    {
        $user = new User();
        $rawCredentialId = random_bytes(32);
        $rawPublicKey = random_bytes(64);
        $aaguid = Uuid::v4();

        $originalRecord = CredentialRecord::create(
            publicKeyCredentialId: $rawCredentialId,
            type: 'public-key',
            transports: ['usb', 'nfc'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: $rawPublicKey,
            userHandle: '100',
            counter: 12,
            otherUI: null,
            backupEligible: false,
            backupStatus: false,
            uvInitialized: true,
        );

        $passkey = $this->mapper->fromCredentialRecord($originalRecord, $user, 'YubiKey 5');
        $reconstructedRecord = $this->mapper->toCredentialRecord($passkey);

        $this->assertSame($originalRecord->publicKeyCredentialId, $reconstructedRecord->publicKeyCredentialId);
        $this->assertSame($originalRecord->type, $reconstructedRecord->type);
        $this->assertSame($originalRecord->transports, $reconstructedRecord->transports);
        $this->assertSame($originalRecord->attestationType, $reconstructedRecord->attestationType);
        $this->assertInstanceOf(EmptyTrustPath::class, $reconstructedRecord->trustPath);
        $this->assertTrue($originalRecord->aaguid->equals($reconstructedRecord->aaguid));
        $this->assertSame($originalRecord->credentialPublicKey, $reconstructedRecord->credentialPublicKey);
        $this->assertSame($originalRecord->userHandle, $reconstructedRecord->userHandle);
        $this->assertSame($originalRecord->counter, $reconstructedRecord->counter);
        $this->assertSame($originalRecord->backupEligible, $reconstructedRecord->backupEligible);
        $this->assertSame($originalRecord->backupStatus, $reconstructedRecord->backupStatus);
        $this->assertSame($originalRecord->uvInitialized, $reconstructedRecord->uvInitialized);
    }

    public function testCertificateTrustPathMapping(): void
    {
        $user = new User();
        $certs = ['MIIBiTCCAS+gAwIBAgIBATAKBggqhkjOPQQDAjAz...'];

        $originalRecord = CredentialRecord::create(
            publicKeyCredentialId: 'test-id',
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'basic',
            trustPath: CertificateTrustPath::create($certs),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: 'test-key',
            userHandle: '1',
            counter: 0,
        );

        $passkey = $this->mapper->fromCredentialRecord($originalRecord, $user, 'Cert Key');
        $this->assertSame(['x5c' => $certs], $passkey->getTrustPath());

        $reconstructed = $this->mapper->toCredentialRecord($passkey);
        $this->assertInstanceOf(CertificateTrustPath::class, $reconstructed->trustPath);
        $this->assertSame($certs, $reconstructed->trustPath->certificates);
    }
}
