<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Beefeater\CrudEventBundle\Repository\AbstractRepository as BundleAbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends BundleAbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }
}
