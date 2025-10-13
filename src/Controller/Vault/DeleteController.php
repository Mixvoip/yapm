<?php

/**
 * @author bsteffan
 * @since 2025-10-07
 */

namespace App\Controller\Vault;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\User;
use App\Entity\Vault;
use App\Message\ProcessDeletionAuditMessage;
use App\Repository\VaultRepository;
use App\Service\Audit\AuditService;
use App\Service\Encryption\EncryptionService;
use DateTimeImmutable;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeleteController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Delete a vault.
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
        "/vaults/{id}/delete",
        name: "api_vaults_id_delete",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"]
    )]
    #[IsGranted('ROLE_ADMIN')]
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

        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        $vault = $vaultRepository->findByIds(
            [$id],
            [
                "PARTIAL v.{id, name, createdAt, updatedAt, deletedAt, deletedBy}",
                "PARTIAL gv.{vault, group, canWrite, partial}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            groupVaultAlias: "gv"
        )[$id] ?? null;

        if (is_null($vault) || !$vault->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Vault with id: $id not found.");
        }

        if (!$vault->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to delete this vault.");
        }

        if ($vault->isPrivate()) {
            throw $this->createAccessDeniedException("You can't delete a private vault.");
        }

        $deletedAt = $vault->markAsDeleted($loggedInUser->getUserIdentifier())
                           ->getDeletedAt();
        $deletedAt = $deletedAt ?? new DateTimeImmutable();
        $this->markVaultContentsAsDeleted($id, $entityManager, $deletedAt);
        $entityManager->flush();

        $bus->dispatch(
            new ProcessDeletionAuditMessage(
                "vault",
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
     * Mark all contents of a vault as deleted.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  DateTimeImmutable  $deletedAt
     *
     * @return void
     * @throws DBALException
     */
    private function markVaultContentsAsDeleted(
        string $id,
        EntityManagerInterface $entityManager,
        DateTimeImmutable $deletedAt
    ): void {
        $userIdentifier = $this->getUser()->getUserIdentifier();
        $db = $entityManager->getConnection();
        $db->executeQuery(
            "UPDATE passwords
                SET deleted_at = :deletedAt,
                    deleted_by = :deletedBy,
                    updated_at = updated_at
                WHERE vault_id = :id
                AND deleted_at IS NULL",
            [
                'id' => $id,
                'deletedBy' => $userIdentifier,
                'deletedAt' => $deletedAt->format("Y-m-d H:i:s"),
            ]
        );

        $db->executeQuery(
            "UPDATE folders
                SET deleted_at = :deletedAt,
                    deleted_by = :deletedBy,
                    updated_at = updated_at
                WHERE vault_id = :id
                AND deleted_at IS NULL",
            [
                'id' => $id,
                'deletedBy' => $userIdentifier,
                'deletedAt' => $deletedAt->format("Y-m-d H:i:s"),
            ]
        );
    }
}
