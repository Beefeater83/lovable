<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Product;
use App\EventListener\ResourceNotFoundExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Beefeater\CrudEventBundle\Exception\ResourceNotFoundException;

class ResourceNotFoundExceptionListenerTest extends TestCase
{
    public function testReturnsJsonResponseForResourceNotFoundException(): void
    {
        $exception = new ResourceNotFoundException(Product::class, 42);

        $event = $this->createEvent($exception);

        $listener = new ResourceNotFoundExceptionListener();
        $listener->onKernelException($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertEquals([
            'error' => 'Product with id 42 not found'
        ], $data);
    }

    private function createEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        return new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );
    }
}
