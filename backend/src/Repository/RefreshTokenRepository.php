<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Beefeater\CrudEventBundle\Repository\AbstractRepository as BundleAbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

class RefreshTokenRepository extends BundleAbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }
}
