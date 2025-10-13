<?php

/**
 * @author bsteffan
 * @since 2025-10-03
 */

namespace App\Controller\Folder;

use App\Entity\Folder;
use App\Entity\Password;
use App\Entity\User;
use App\Normalizer\PasswordNormalizer;
use App\Repository\FolderRepository;
use App\Repository\PasswordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetPasswordsController extends AbstractController
{
    /**
     * Get all passwords for a folder.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     */
    #[Route(
        "/folders/{id}/passwords",
        name: "folder_id_passwords",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        /** @var FolderRepository $folderRepository */
        $folderRepository = $entityManager->getRepository(Folder::class);
        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id}",
                "PARTIAL fg.{folder, group, canWrite}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            folderGroupAlias: "fg"
        )[$id] ?? null;

        if (is_null($folder) || !$folder->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Folder with id: $id not found.");
        }

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $entityManager->getRepository(Password::class);
        $passwords = $passwordRepository->findForFolder(
            $folder->getId(),
            $loggedInUser->getGroupIds(),
            [
                "PARTIAL p.{id, title, target, location, description, externalId, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL gp.{group, password, canWrite}",
                "PARTIAL g.{id, name, private}",
            ],
        );

        return $this->json($passwords, context: [PasswordNormalizer::WITH_GROUPS]);
    }
}
