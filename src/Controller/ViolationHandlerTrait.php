<?php

/**
 * @author bsteffan
 * @since 2025-10-19
 */

namespace App\Controller;

use App\Exception\InvalidRequestBodyException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

trait ViolationHandlerTrait
{
    /** @var ConstraintViolation[] */
    private array $violations = [];

    /**
     * @return bool
     */
    protected function hasViolations(): bool
    {
        return !empty($this->violations);
    }

    /**
     * @param  ConstraintViolation|ConstraintViolation[]  $violation
     *
     * @return void
     */
    protected function addViolation(ConstraintViolation|array $violation): void
    {
        if (!is_array($violation)) {
            $this->violations[] = $violation;
        } else {
            $this->violations = array_merge($this->violations, $violation);
        }
    }

    /**
     * @throws InvalidRequestBodyException
     */
    protected function throwViolations(): void
    {
        if (empty($this->violations)) {
            return;
        }

        throw new InvalidRequestBodyException(new ConstraintViolationList($this->violations));
    }
}
