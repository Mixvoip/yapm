<?php

/**
 * @author bsteffan
 * @since 2025-10-19
 */

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

class InvalidRequestBodyException extends UnprocessableEntityHttpException
{
    /**
     * @param  ConstraintViolationListInterface  $violations
     * @param  string  $message
     * @param  Throwable|null  $previous
     */
    public function __construct(
        private readonly ConstraintViolationListInterface $violations,
        string $message = 'Validation failed',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $previous, 422);
    }

    /**
     * @return ConstraintViolationListInterface
     */
    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
