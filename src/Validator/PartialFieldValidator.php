<?php

/**
 * @author bsteffan
 * @since 2025-06-20
 */

namespace App\Validator;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PartialFieldValidator
{
    /**
     * Custom Validation for nullable strings.
     *
     * @param  string  $fieldName
     * @param  string|false|null  $value
     * @param  ExecutionContextInterface  $context
     * @param  int|null  $maxLength
     *
     * @return void
     */
    public static function validateNullableString(
        string $fieldName,
        string|false|null $value,
        ExecutionContextInterface $context,
        ?int $maxLength = null
    ): void {
        if ($value === false || $value === null) {
            return; // Field is not being patched, or if it is null, we don't need to validate it.
        }

        if (!is_string($value)) {
            $context->buildViolation(ucfirst($fieldName) . ' must be a string.')
                    ->atPath($fieldName)
                    ->addViolation();
            return;
        }

        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            $context->buildViolation(ucfirst($fieldName) . " cannot be longer than $maxLength characters.")
                    ->atPath($fieldName)
                    ->addViolation();
        }
    }
}
