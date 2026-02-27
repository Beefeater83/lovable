<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use App\Services\ImageStorageService;
use Beefeater\CrudEventBundle\Event\CrudAfterEntityDelete;

class ProductDeleteListener
{
    private ImageStorageService $imageStorage;

    public function __construct(ImageStorageService $imageStorage)
    {
        $this->imageStorage = $imageStorage;
    }
    public function onAfterDelete(CrudAfterEntityDelete $event): void
    {
        $product = $event->getEntity();
        if (!$product instanceof Product) {
            return;
        }

        $imagePath = $product->getImagePath();
        if (!$imagePath) {
            return;
        }

        $this->imageStorage->remove($imagePath);
    }
}
