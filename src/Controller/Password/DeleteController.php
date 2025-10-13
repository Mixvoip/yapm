<?php

/**
 * @author bsteffan
 * @since 2025-10-02
 */

namespace App\Controller\Password;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\Password;
use App\Entity\User;
use App\Repository\PasswordRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class DeleteController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Delete a password.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     *
     * @return Response
     */
    #[Route(
        "/passwords/{id}/delete",
        name: "passwords_id_delete",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"]
    )]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
        #[MapRequestPayload] EncryptedClientDataDto $encryptedPassword,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService
    ): Response {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;

        try {
            $this->decryptUserPrivateKey($encryptedPassword);
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $entityManager->getRepository(Password::class);
        $password = $passwordRepository->findByIds(
            [$id],
            [
                "PARTIAL p.{id, title, externalId, target, createdAt, updatedAt, deletedAt, deletedBy, updatedBy}",
                "PARTIAL gp.{group, password, canWrite}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            groupPasswordAlias: "gp"
        )[$id] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }

        if (!$password->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to delete this password.");
        }

        $password->markAsDeleted($loggedInUser->getUserIdentifier());
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
