<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 */

namespace App\Controller\Folder\Dto;

use App\Validator\PartialFieldValidator;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class PatchDto
{
    /**
     * @param  string|null  $name
     * @param  string|false|null  $externalId
     */
    public function __construct(
        #[
            Assert\Type('string'),
            Assert\NotEqualTo(""),
            Assert\Length(max: 128),
        ]
        private ?string $name = null,

        private string|false|null $externalId = false,
    ) {
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
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
     * Custom validation for the externalId field.
     *
     * @param  ExecutionContextInterface  $context
     *
     * @return void
     */
    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        PartialFieldValidator::validateNullableString("externalId", $this->externalId, $context, 255);
    }
}
