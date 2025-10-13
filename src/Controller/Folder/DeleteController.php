<?php

/**
 * @author bsteffan
 * @since 2025-10-07
 */

namespace App\Controller\Folder;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\Folder;
use App\Entity\User;
use App\Message\ProcessDeletionAuditMessage;
use App\Repository\FolderRepository;
use App\Service\Audit\AuditService;
use App\Service\Encryption\EncryptionService;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class DeleteController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Delete a folder.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     * @param  MessageBusInterface  $bus
     * @param  Request  $request
     *
     * @return Response
     * @throws DBALException
     * @throws ExceptionInterface
     */
    #[Route(
        "/folders/{id}/delete",
        name: "folder_id_delete",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"],
    )]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
        #[MapRequestPayload] EncryptedClientDataDto $encryptedPassword,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService,
        MessageBusInterface $bus,
        Request $request
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

        /** @var FolderRepository $folderRepository */
        $folderRepository = $entityManager->getRepository(Folder::class);
        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id, name, externalId, createdAt, updatedAt, deletedAt, deletedBy}",
                "PARTIAL fg.{folder, group, canWrite, partial}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            folderGroupAlias: "fg",
        )[$id] ?? null;

        if (is_null($folder) || !$folder->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Folder with id: $id not found.");
        }

        if (!$folder->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to delete this folder.");
        }

        $deletedAt = $folder->markAsDeleted($loggedInUser->getUserIdentifier())
                            ->getDeletedAt();
        $deletedAt = $deletedAt ?? new DateTimeImmutable();
        $this->markFolderContentsAsDeleted($id, $entityManager, $deletedAt);
        $entityManager->flush();

        $bus->dispatch(
            new ProcessDeletionAuditMessage(
                "folder",
                $id,
                $loggedInUser->getUserIdentifier(),
                $deletedAt,
                AuditService::getClientIp($request),
                $request->headers->get('User-Agent')
            )
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Mark all contents of a folder as deleted.
     *
     * @param  string  $folderId
     * @param  EntityManagerInterface  $entityManager
     * @param  DateTimeImmutable  $deletedAt
     *
     * @return void
     * @throws DBALException
     */
    private function markFolderContentsAsDeleted(
        string $folderId,
        EntityManagerInterface $entityManager,
        DateTimeImmutable $deletedAt
    ): void {
        $userIdentifier = $this->getUser()->getUserIdentifier();
        $db = $entityManager->getConnection();

        $allChildren = [];
        $front = [$folderId];

        while (!empty($front)) {
            $children = $db->executeQuery(
                "SELECT id FROM folders WHERE parent_folder_id IN (:ids) AND deleted_at IS NULL",
                ['ids' => array_values($front)],
                ['ids' => ArrayParameterType::STRING]
            )
                           ->fetchFirstColumn();

            $children = array_values(array_diff($children, $allChildren));
            if (empty($children)) {
                break;
            }

            $allChildren = array_merge($allChildren, $children);
            $front = $children;
        }

        $db->executeQuery(
            "UPDATE folders
                SET deleted_at = :deletedAt,
                    deleted_by = :deletedBy,
                    updated_at = updated_at
                WHERE id IN (:ids)
                AND deleted_at IS NULL",
            [
                'ids' => $allChildren,
                'deletedBy' => $userIdentifier,
                'deletedAt' => $deletedAt->format("Y-m-d H:i:s"),
            ],
            ['ids' => ArrayParameterType::STRING]
        );

        $all = array_merge($allChildren, [$folderId]);
        $db->executeQuery(
            "UPDATE passwords
                SET deleted_at = :deletedAt,
                    deleted_by = :deletedBy,
                    updated_at = updated_at
                WHERE folder_id IN (:ids)
                AND deleted_at IS NULL",
            [
                'ids' => $all,
                'deletedBy' => $userIdentifier,
                'deletedAt' => $deletedAt->format("Y-m-d H:i:s"),
            ],
            ['ids' => ArrayParameterType::STRING]
        );
    }
}
