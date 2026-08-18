<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Passkey;
use App\Entity\User;
use Beefeater\CrudEventBundle\Exception\ResourceNotFoundException;
use Beefeater\CrudEventBundle\Repository\AbstractRepository as BundleAbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BundleAbstractRepository<Passkey>
 */
class PasskeyRepository extends BundleAbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Passkey::class);
    }

    public function findOneByCredentialId(string $credentialId): Passkey
    {
        $passkey = $this->findOneBy(['credentialId' => $credentialId]);
        if (!$passkey) {
            throw new ResourceNotFoundException(Passkey::class, $credentialId);
        }

        return $passkey;
    }

    /**
     * @return Passkey[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'ASC']);
    }
}
