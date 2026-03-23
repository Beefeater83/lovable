<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use Beefeater\CrudEventBundle\Event\CrudBeforeEntityDelete;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProductBeforeDeleteListener
{
    public function __construct(private AuthorizationCheckerInterface $authChecker)
    {
    }
    public function onBeforeDelete(CrudBeforeEntityDelete $event): void
    {
        $product = $event->getEntity();
        if (!$product instanceof Product) {
            return;
        }

        if (!$this->authChecker->isGranted('PRODUCT_DELETE', $product)) {
            throw new AccessDeniedException('You are not allowed to delete this product');
        }
    }
}
