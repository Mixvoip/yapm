<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 */

namespace App\Controller\Password\Dto;

use App\Validator\PartialFieldValidator;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class PatchDto
{
    /**
     * @param  string|null  $title
     * @param  string|false|null  $description
     * @param  string|false|null  $target
     * @param  string|false|null  $location
     * @param  string|false|null  $externalId
     */
    public function __construct(
        #[
            Assert\Length(max: 255),
            Assert\NotEqualTo(''),
            Assert\Type('string'),
        ]
        private ?string $title = null,

        private string|false|null $description = false,
        private string|false|null $target = false,
        private string|false|null $location = false,
        private string|false|null $externalId = false
    ) {
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return false|string|null
     */
    public function getDescription(): false|string|null
    {
        if ($this->description === "") {
            return null;
        }

        return $this->description;
    }

    /**
     * @return false|string|null
     */
    public function getTarget(): false|string|null
    {
        if ($this->target === "") {
            return null;
        }

        return $this->target;
    }

    /**
     * @return false|string|null
     */
    public function getLocation(): false|string|null
    {
        if ($this->location === "") {
            return null;
        }

        return $this->location;
    }

    /**
     * @return false|string|null
     */
    public function getExternalId(): false|string|null
    {
        if ($this->externalId === "") {
            return null;
        }

        return $this->externalId;
    }

    /**
     * Custom validation for the description and target fields.
     *
     * @param  ExecutionContextInterface  $context
     *
     * @return void
     */
    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        PartialFieldValidator::validateNullableString("description", $this->description, $context);
        PartialFieldValidator::validateNullableString("target", $this->target, $context, 255);
        PartialFieldValidator::validateNullableString("location", $this->location, $context, 255);
        PartialFieldValidator::validateNullableString("externalId", $this->externalId, $context, 255);
    }
}
