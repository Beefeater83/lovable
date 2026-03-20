<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Beefeater\CrudEventBundle\Exception\PayloadValidationException as BundleValidationException;

class PayloadValidationExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof BundleValidationException) {
            $violations = $exception->getViolations();

            $errorsArray = [];

            foreach ($violations as $violation) {
                $errorsArray[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }

            if (empty($errorsArray)) {
                $errorsArray = ['Unknown validation error'];
            }

            $response = new JsonResponse(['error' => $errorsArray], Response::HTTP_BAD_REQUEST);

            $event->setResponse($response);
        }
    }
}
