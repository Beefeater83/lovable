<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Beefeater\CrudEventBundle\Repository\AbstractRepository as BundleAbstractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class RefreshTokenRepository extends BundleAbstractRepository
{
    private EntityManagerInterface $entityManager;
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, RefreshToken::class);
        $this->entityManager = $entityManager;
    }

    public function remove($entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
