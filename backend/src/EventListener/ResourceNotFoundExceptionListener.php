<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Beefeater\CrudEventBundle\Exception\ResourceNotFoundException as BundleResourceNotFoundException;

class ResourceNotFoundExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (
            $exception instanceof BundleResourceNotFoundException
        ) {
            $response = new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
            $event->setResponse($response);
        }
    }
}
