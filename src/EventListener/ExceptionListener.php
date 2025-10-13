<?php

/**
 * @author bsteffan
 * @since 2025-04-25
 */

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

#[AsEventListener(event: 'kernel.exception')]
class ExceptionListener
{
    /**
     * @param  ExceptionEvent  $event
     *
     * @return void
     */
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $statusCode = 500;
        $error = 'Internal Server Error';

        if ($exception instanceof NotFoundHttpException) {
            $statusCode = 404;
            $error = 'Resource not found';
            $message = $exception->getMessage();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $error = 'HTTP Error';
            $message = $exception->getMessage();

            if ($statusCode === 422) {
                $previous = $exception->getPrevious();
                if (!is_null($previous) && method_exists($previous, 'getViolations')) {
                    /** @var ConstraintViolationListInterface $violations */
                    $violations = $previous->getViolations();
                    $message = [];
                    foreach ($violations as $violation) {
                        $message[] = [
                            'parameter' => $violation->getPropertyPath(),
                            'message' => $violation->getMessage(),
                            'code' => $violation->getCode(),
                        ];
                    }
                    $error = 'Unprocessable Entity';
                } else {
                    $message = !empty($message) ? $message : 'Unprocessable Entity';
                }
            }
        } else {
            $message = $exception->getMessage();
        }

        $response = new JsonResponse(
            [
                'error' => $error,
                'message' => $message,
            ],
            $statusCode
        );

        $event->setResponse($response);
    }
}
