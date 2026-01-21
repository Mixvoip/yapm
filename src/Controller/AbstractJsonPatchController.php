<?php

/**
 * @author bsteffan
 * @since 2025-09-16
 * @noinspection PhpMultipleClassDeclarationsInspection DateMalformedStringException
 */

namespace App\Controller;

use App\Exception\InvalidRequestBodyException;
use App\Service\Attributes\DefaultPatchConfiguration;
use DateMalformedStringException;
use DateTimeImmutable;
use ReflectionClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

abstract class AbstractJsonPatchController extends AbstractController
{
    use ViolationHandlerTrait;

    private array $requestData = [];
    private array $patchData = [];

    /**
     * Initialize the patch data from the request.
     *
     * @param  Request  $request
     *
     * @return void
     */
    protected function initializePatchData(Request $request): void
    {
        $this->requestData = json_decode($request->getContent(), true);
        $this->patchData = [];
    }

    /**
     * Check if a key is present in the patch request.
     *
     * @param  string  $key
     *
     * @return bool
     */
    protected function isPatchRequested(string $key): bool
    {
        return array_key_exists($key, $this->requestData);
    }

    /**
     * Add patch data to the patch data array.
     *
     * @param  string  $key
     * @param  mixed  $oldValue
     * @param  mixed  $newValue
     * @param  callable  $setter
     *
     * @return void
     */
    protected function addPatchData(string $key, mixed $oldValue, mixed $newValue, callable $setter): void
    {
        $this->patchData[$key] = [
            'oldValue' => $oldValue,
            'newValue' => $newValue,
            'setter' => $setter,
        ];
    }

    /**
     * Add patch data based on DTO.
     * Use the `PatchExclude` attribute to exclude properties in the DTO to be patched by default.
     * Use the `PatchConfiguration` attribute to set customer getters and/or setters.
     *
     * @param  object  $patchObject
     * @param  object  $dto
     *
     * @return void
     * @throws DateMalformedStringException
     */
    protected function addDefaultPatchData(object $patchObject, object $dto): void
    {
        $reflection = new ReflectionClass($dto);
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $name = $property->getName();

            $attributes = [];
            foreach ($property->getAttributes() as $attribute) {
                $attributes[$attribute->getName()] = $attribute->newInstance();
            }

            $value = $dto->$name;

            if (isset($attributes[DefaultPatchConfiguration::class])) {
                /** @var DefaultPatchConfiguration $attribute */
                $attribute = $attributes[DefaultPatchConfiguration::class];
                if ($attribute->isIgnore()) {
                    continue;
                }

                if (!is_null($attribute->getNormalizer())) {
                    $value = $attribute->getNormalizer()($value);
                }
            }

            $setter = "set" . ucfirst($name);
            if (!method_exists($patchObject, $setter)) {
                throw new RuntimeException("Unable to guess a setter for '$name' in " . $patchObject::class . ".");
            }

            $getter = "get" . ucfirst($name);
            if (!method_exists($patchObject, $getter)) {
                $getter = "is" . ucfirst($name);
                if (!method_exists($patchObject, $getter)) {
                    $getter = "has" . ucfirst($name);
                    if (!method_exists($patchObject, $getter)) {
                        $getter = "can" . ucfirst($name);
                    }
                }
            }
            if (!method_exists($patchObject, $getter)) {
                throw new RuntimeException("Unable to guess a getter for '$name' in " . $patchObject::class . ".");
            }

            if (
                is_string($value)
                && (
                    isset($attributes[Assert\DateTime::class])
                    || isset($attributes[Assert\Date::class])
                )
            ) {
                $value = new DateTimeImmutable($value);
            }

            $this->addPatchData(
                $name,
                $patchObject->$getter(),
                $value,
                [$patchObject, $setter]
            );
        }
    }

    /**
     * Patch values and return whether something was updated.
     *
     * @param  bool  $throwViolations
     *
     * @return bool
     * @throws InvalidRequestBodyException
     */
    protected function patch(bool $throwViolations = true): bool
    {
        if ($throwViolations) {
            $this->throwViolations();
        }

        $patched = false;
        foreach ($this->patchData as $key => $patch) {
            if (!$this->isPatchRequested($key)) {
                continue;
            }

            if (
                is_object($patch['oldValue'])
                && is_object($patch['newValue'])
                && method_exists($patch['oldValue'], "getId")
                && method_exists($patch['newValue'], "getId")
            ) {
                if ($patch['oldValue']->getId() === $patch['newValue']->getId()) {
                    continue;
                }
            } elseif ($patch['oldValue'] === $patch['newValue']) {
                continue;
            }

            $patch['setter']($patch['newValue']);
            $patched = true;
        }

        return $patched;
    }
}
