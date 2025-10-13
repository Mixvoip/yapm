<?php

/**
 * @author bsteffan
 * @since 2025-07-15
 * @noinspection PhpMultipleClassDeclarationsInspection DateMalformedStringException
 */

namespace App\Controller\QueryParameterResolver;

use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class DateTimeResolver implements ValueResolverInterface
{
    /**
     * @param  ArgumentMetadata  $argument
     *
     * @return string
     */
    private function getName(ArgumentMetadata $argument): string
    {
        $attribute = $argument->getAttributesOfType(MapQueryParameter::class)[0];
        return $attribute->name ?? $argument->getName();
    }

    /**
     * @param  Request  $request
     * @param  ArgumentMetadata  $argument
     * @param  string|Closure  $type
     *
     * @return bool
     */
    private function supports(Request $request, ArgumentMetadata $argument, string|Closure $type): bool
    {
        if ($type instanceof Closure) {
            $supportsType = $type($argument->getType());
        } else {
            $supportsType = is_a($argument->getType(), $type, true);
        }

        return $supportsType && $request->query->has($this->getName($argument));
    }

    /**
     * @inheritDoc
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$this->supports($request, $argument, DateTimeImmutable::class)) {
            return [];
        }

        $name = $this->getName($argument);
        $value = $request->query->get($name);
        if (empty($value)) {
            if ($argument->isNullable()) {
                return [null];
            }

            throw $this->badRequestException($name);
        }

        try {
            $value = new DateTimeImmutable($value);
        } catch (DateMalformedStringException) {
            throw $this->badRequestException($name);
        }

        return [$value];
    }

    /**
     * @param  string  $parameter
     *
     * @return BadRequestException
     */
    private function badRequestException(string $parameter): BadRequestException
    {
        return new BadRequestException("Invalid date format for parameter: $parameter.");
    }
}
