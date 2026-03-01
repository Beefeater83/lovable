<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Beefeater\CrudEventBundle\Repository\AbstractRepository as BundleAbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends BundleAbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findAdmin(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->andWhere('u.roles LIKE :roles')
            ->setParameter('email', $email)
            ->setParameter('roles', '%' . User::ROLE_ADMIN . '%')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
