<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Product;
use App\EventListener\ProductAfterDeleteListener;
use App\Services\ImageStorageService;
use PHPUnit\Framework\TestCase;
use Beefeater\CrudEventBundle\Event\CrudAfterEntityDelete;

class ProductAfterDeleteListenerTest extends TestCase
{
    public function testRemovesImageWhenProductHasImage(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getImagePath')->willReturn('image.png');

        $imageStorage = $this->createMock(ImageStorageService::class);
        $imageStorage
            ->expects($this->once())
            ->method('remove')
            ->with('image.png');

        $event = $this->createEvent($product);

        $listener = new ProductAfterDeleteListener($imageStorage);
        $listener->onAfterDelete($event);
    }

    public function testDoesNothingWhenNoImage(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getImagePath')->willReturn('');

        $imageStorage = $this->createMock(ImageStorageService::class);
        $imageStorage
            ->expects($this->never())
            ->method('remove');

        $event = $this->createEvent($product);

        $listener = new ProductAfterDeleteListener($imageStorage);
        $listener->onAfterDelete($event);
    }

    public function testDoesNothingWhenEntityIsNotProduct(): void
    {
        $notProduct = new \stdClass();

        $imageStorage = $this->createMock(ImageStorageService::class);
        $imageStorage
            ->expects($this->never())
            ->method('remove');

        $event = $this->createEvent($notProduct);

        $listener = new ProductAfterDeleteListener($imageStorage);
        $listener->onAfterDelete($event);
    }

    private function createEvent(object $entity): CrudAfterEntityDelete
    {
        $event = $this->createMock(CrudAfterEntityDelete::class);
        $event->method('getEntity')->willReturn($entity);

        return $event;
    }
}
