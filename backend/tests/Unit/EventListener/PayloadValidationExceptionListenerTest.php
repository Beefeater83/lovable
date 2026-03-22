<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Product;
use App\EventListener\PayloadValidationExceptionListener;
use Beefeater\CrudEventBundle\Exception\PayloadValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class PayloadValidationExceptionListenerTest extends TestCase
{
    public function testProductValidationErrorsAreFormattedCorrectly(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Name should not be blank.',
                '',
                [],
                null,
                'name',
                ''
            ),
            new ConstraintViolation(
                'Price must be greater than 0.',
                '',
                [],
                null,
                'price',
                -10
            ),
            new ConstraintViolation(
                'Invalid category. Allowed: phone, notebook, headphones.',
                '',
                [],
                null,
                'category',
                'tv'
            ),
        ]);

        $exception = new PayloadValidationException(
            Product::class,
            $violations
        );

        $event = $this->createEvent($exception);

        $listener = new PayloadValidationExceptionListener();
        $listener->onKernelException($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertEquals([
            'error' => [
                'name: Name should not be blank.',
                'price: Price must be greater than 0.',
                'category: Invalid category. Allowed: phone, notebook, headphones.',
            ]
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
