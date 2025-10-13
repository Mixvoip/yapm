<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 */

namespace App\Controller\Password;

use App\Controller\Password\Dto\PatchDto;
use App\Entity\Enums\PasswordField;
use App\Entity\Password;
use App\Entity\User;
use App\Normalizer\PasswordNormalizer;
use App\Repository\PasswordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PatchController extends AbstractController
{
    /**
     * Update a password.
     *
     * @param  string  $id
     * @param  PatchDto  $patchDto
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
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
                "PARTIAL f.{id, name}",
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

        $mandatoryPasswordFields = $password->getVault()->getMandatoryPasswordFields() ?? [];
        $updated = false;
        if ($patchDto->getDescription() !== false && $patchDto->getDescription() !== $password->getDescription()) {
            $password->setDescription($patchDto->getDescription());
            $updated = true;
        }

        if ($patchDto->getTarget() !== false && $patchDto->getTarget() !== $password->getTarget()) {
            if (is_null($patchDto->getTarget()) && in_array(PasswordField::Target, $mandatoryPasswordFields)) {
                throw new BadRequestHttpException("Target is mandatory for this vault.");
            }

            $password->setTarget($patchDto->getTarget());
            $updated = true;
        }

        if (!is_null($patchDto->getTitle()) && $patchDto->getTitle() !== $password->getTitle()) {
            $password->setTitle($patchDto->getTitle());
            $updated = true;
        }

        if ($patchDto->getLocation() !== false && $patchDto->getLocation() !== $password->getLocation()) {
            if (is_null($patchDto->getLocation()) && in_array(PasswordField::Location, $mandatoryPasswordFields)) {
                throw new BadRequestHttpException("Location is mandatory for this vault.");
            }

            $password->setLocation($patchDto->getLocation());
            $updated = true;
        }

        if ($patchDto->getExternalId() !== false && $patchDto->getExternalId() !== $password->getExternalId()) {
            if (is_null($patchDto->getExternalId()) && in_array(PasswordField::ExternalId, $mandatoryPasswordFields)) {
                throw new BadRequestHttpException("ExternalId is mandatory for this vault.");
            }

            $password->setExternalId($patchDto->getExternalId());
            $updated = true;
        }

        if ($updated) {
            $password->setUpdatedBy($loggedInUser->getUserIdentifier());
            $entityManager->persist($password);
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
}
