<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Product;
use App\EventListener\ProductBeforeDeleteListener;
use PHPUnit\Framework\TestCase;
use Beefeater\CrudEventBundle\Event\CrudBeforeEntityDelete;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProductBeforeDeleteListenerTest extends TestCase
{
    public function testAllowsDeleteWhenGranted(): void
    {
        $product = $this->createMock(Product::class);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('PRODUCT_DELETE', $product)
            ->willReturn(true);

        $event = $this->createEvent($product);

        $listener = new ProductBeforeDeleteListener($authChecker);

        $listener->onBeforeDelete($event);

        $this->assertTrue(true);
    }

    public function testThrowsExceptionWhenNotGranted(): void
    {
        $product = $this->createMock(Product::class);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('PRODUCT_DELETE', $product)
            ->willReturn(false);

        $event = $this->createEvent($product);

        $listener = new ProductBeforeDeleteListener($authChecker);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You are not allowed to delete this product');

        $listener->onBeforeDelete($event);
    }

    public function testDoesNothingWhenEntityIsNotProduct(): void
    {
        $notProduct = new \stdClass();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker
            ->expects($this->never())
            ->method('isGranted');

        $event = $this->createEvent($notProduct);

        $listener = new ProductBeforeDeleteListener($authChecker);
        $listener->onBeforeDelete($event);
    }

    private function createEvent(object $entity): CrudBeforeEntityDelete
    {
        $event = $this->createMock(CrudBeforeEntityDelete::class);
        $event->method('getEntity')->willReturn($entity);

        return $event;
    }
}
