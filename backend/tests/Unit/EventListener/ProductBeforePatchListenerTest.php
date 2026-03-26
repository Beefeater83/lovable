<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Product;
use App\EventListener\ProductBeforePatchListener;
use Beefeater\CrudEventBundle\Event\CrudBeforeEntityPersist;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProductBeforePatchListenerTest extends TestCase
{
    public function testAllowsPatchWhenGranted(): void
    {
        $product = $this->createMock(Product::class);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('PRODUCT_PATCH', $product)
            ->willReturn(true);

        $event = $this->createEvent($product);

        $listener = new ProductBeforePatchListener($authChecker);

        $listener->onBeforePersist($event);

        $this->assertTrue(true);
    }

    public function testThrowsExceptionWhenNotGranted(): void
    {
        $product = $this->createMock(Product::class);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('PRODUCT_PATCH', $product)
            ->willReturn(false);

        $event = $this->createEvent($product);

        $listener = new ProductBeforePatchListener($authChecker);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You are not allowed to patch this product');

        $listener->onBeforePersist($event);
    }

    public function testDoesNothingWhenEntityIsNotProduct(): void
    {
        $notProduct = new \stdClass();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker
            ->expects($this->never())
            ->method('isGranted');

        $event = $this->createEvent($notProduct);

        $listener = new ProductBeforePatchListener($authChecker);
        $listener->onBeforePersist($event);
    }

    private function createEvent(object $entity): CrudBeforeEntityPersist
    {
        $event = $this->createMock(CrudBeforeEntityPersist::class);
        $event->method('getEntity')->willReturn($entity);

        return $event;
    }
}
