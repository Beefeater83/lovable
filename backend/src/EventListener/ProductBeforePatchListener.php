<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use Beefeater\CrudEventBundle\Event\CrudBeforeEntityDelete;
use Beefeater\CrudEventBundle\Event\CrudBeforeEntityPersist;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProductBeforePatchListener
{
    public function __construct(private AuthorizationCheckerInterface $authChecker) {}
    public function onBeforePersist(CrudBeforeEntityPersist $event): void
    {
        $product = $event->getEntity();
        if (!$product instanceof Product) {
            return;
        }

        if (!$this->authChecker->isGranted('PRODUCT_PATCH', $product)) {
            throw new AccessDeniedException('You are not allowed to edit this product');
        }
    }
}
