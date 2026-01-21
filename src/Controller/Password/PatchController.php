<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 * @noinspection PhpMultipleClassDeclarationsInspection DateMalformedStringException
 */

namespace App\Controller\Password;

use App\Controller\AbstractJsonPatchController;
use App\Controller\NulledValueGetterTrait;
use App\Controller\Password\Dto\PatchDto;
use App\Entity\Enums\PasswordField;
use App\Entity\Password;
use App\Entity\User;
use App\Normalizer\PasswordNormalizer;
use App\Repository\PasswordRepository;
use DateMalformedStringException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;

class PatchController extends AbstractJsonPatchController
{
    use NulledValueGetterTrait;

    /**
     * Update a password.
     *
     * @param  string  $id
     * @param  PatchDto  $patchDto
     * @param  Request  $request
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     * @throws DateMalformedStringException
     */
    #[Route(
        "/passwords/{id}",
        name: "api_passwords_id_patch",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["PATCH"]
    )]
    public function index(
        string $id,
        #[MapRequestPayload] PatchDto $patchDto,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $entityManager->getRepository(Password::class);
        $password = $passwordRepository->findByIds(
            [$id],
            [
                "PARTIAL p.{id, title, description, location, externalId, target, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL f.{id, name, iconName}",
                "PARTIAL gp.{group, password, canWrite}",
                "PARTIAL g.{id, name, private}",
                "PARTIAL v.{id, name, mandatoryPasswordFields}",
            ],
            folderAlias: "f",
            groupAlias: "g",
            groupPasswordAlias: "gp",
            vaultAlias: "v"
        )[$id] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }

        if (!$password->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to update this password.");
        }

        $this->initializePatchData($request);
        $this->addDefaultPatchData($password, $patchDto);
        $mandatoryPasswordFields = $password->getVault()->getMandatoryPasswordFields() ?? [];

        $this->addExternalIdPatchData($password, $patchDto->externalId, $mandatoryPasswordFields);
        $this->addLocationPatchData($password, $patchDto->location, $mandatoryPasswordFields);
        $this->addTargetPatchData($password, $patchDto->target, $mandatoryPasswordFields);

        if ($this->patch()) {
            $password->setUpdatedBy($loggedInUser->getUserIdentifier());
            $entityManager->flush();
        }

        return $this->json(
            $password,
            context: [
                PasswordNormalizer::WITH_FOLDER,
                PasswordNormalizer::WITH_GROUPS,
            ]
        );
    }

    /**
     * Add externalId to patch data if requested and valid.
     *
     * @param  Password  $password
     * @param  string|null  $externalId
     * @param  PasswordField[]  $mandatoryPasswordFields
     *
     * @return void
     */
    private function addExternalIdPatchData(
        Password $password,
        ?string $externalId,
        array $mandatoryPasswordFields
    ): void {
        if (!$this->isPatchRequested("externalId")) {
            return;
        }

        $externalId = self::getTrimmedOrNull($externalId);
        if (is_null($externalId) && in_array(PasswordField::ExternalId, $mandatoryPasswordFields)) {
            $violation = new ConstraintViolation(
                "ExternalId is mandatory for this vault.",
                null,
                [],
                $externalId,
                "externalId",
                $externalId
            );
            $this->addViolation($violation);
            return;
        }

        $this->addPatchData(
            "externalId",
            $password->getExternalId(),
            $externalId,
            [$password, "setExternalId"]
        );
    }

    /**
     * Add location to patch data if requested and valid.
     *
     * @param  Password  $password
     * @param  string|null  $location
     * @param  PasswordField[]  $mandatoryPasswordFields
     *
     * @return void
     */
    private function addLocationPatchData(Password $password, ?string $location, array $mandatoryPasswordFields): void
    {
        if (!$this->isPatchRequested("location")) {
            return;
        }

        $location = self::getTrimmedOrNull($location);
        if (is_null($location) && in_array(PasswordField::Location, $mandatoryPasswordFields)) {
            $violation = new ConstraintViolation(
                "Location is mandatory for this vault.",
                null,
                [],
                $location,
                "location",
                $location
            );
            $this->addViolation($violation);
            return;
        }

        $this->addPatchData(
            "location",
            $password->getLocation(),
            $location,
            [$password, "setLocation"]
        );
    }

    /**
     * Add target to patch data if requested and valid.
     *
     * @param  Password  $password
     * @param  string|null  $target
     * @param  PasswordField[]  $mandatoryPasswordFields
     *
     * @return void
     */
    private function addTargetPatchData(Password $password, ?string $target, array $mandatoryPasswordFields): void
    {
        if (!$this->isPatchRequested("target")) {
            return;
        }

        $target = self::getTrimmedOrNull($target);
        if (is_null($target) && in_array(PasswordField::Target, $mandatoryPasswordFields)) {
            $violation = new ConstraintViolation(
                "Target is mandatory for this vault.",
                null,
                [],
                $target,
                "target",
                $target
            );
            $this->addViolation($violation);
            return;
        }

        $this->addPatchData(
            "target",
            $password->getTarget(),
            $target,
            [$password, "setTarget"]
        );
    }
}
