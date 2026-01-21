<?php

/**
 * @author bsteffan
 * @since 2025-10-07
 */

namespace App\Message;

use App\Entity\Enums\AuditAction;
use App\Entity\Folder;
use App\Entity\Password;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(sign: true)]
class ProcessDeletionAuditMessageHandler
{
    private const int BATCH_SIZE = 100;
    private const int BULK_INSERT_SIZE = 50;

    private array $auditLogQueue = [];
    private ?string $userId = null;

    /**
     * @param  EntityManagerInterface  $entityManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param  ProcessDeletionAuditMessage  $message
     *
     * @return void
     * @throws DBALException
     */
    public function __invoke(ProcessDeletionAuditMessage $message): void
    {
        $userId = $this->db()->fetchOne("SELECT id FROM users WHERE email = :email", ['email' => $message->deletedBy]);
        if ($userId !== false) {
            $this->userId = $userId;
        }

        try {
            match ($message->entityType) {
                'vault' => $this->processVaultDeletion($message),
                'folder' => $this->processFolderDeletion($message),
                default => throw new InvalidArgumentException("Unknown entity type: $message->entityType"),
            };
        } finally {
            $this->flushAuditLogs();
        }
    }

    /**
     * Get the database connection.
     *
     * @return Connection
     */
    private function db(): Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * Process vault deletion audit.
     * Note: The vault itself is already audited by DoctrineAuditListener.
     * We only audit the cascaded children.
     *
     * @param  ProcessDeletionAuditMessage  $message
     *
     * @return void
     * @throws DBALException
     */
    private function processVaultDeletion(ProcessDeletionAuditMessage $message): void
    {
        // Get all folders in this vault that were deleted in this operation
        $folderIds = $this->db()->fetchFirstColumn(
            "SELECT id FROM folders 
             WHERE vault_id = :vaultId 
             AND deleted_by = :deletedBy
             AND deleted_at = :deletedAt",
            [
                'vaultId' => $message->entityId,
                'deletedBy' => $message->deletedBy,
                'deletedAt' => $message->deletedAt->format("Y-m-d H:i:s"),
            ]
        );

        // Get all passwords in this vault that were deleted in this operation
        $passwordIds = $this->db()->fetchFirstColumn(
            "SELECT id FROM passwords 
             WHERE vault_id = :vaultId 
             AND deleted_by = :deletedBy
             AND deleted_at = :deletedAt",
            [
                'vaultId' => $message->entityId,
                'deletedBy' => $message->deletedBy,
                'deletedAt' => $message->deletedAt->format("Y-m-d H:i:s"),
            ]
        );

        // Audit all affected entities
        $this->auditFolders($folderIds, $message);
        $this->auditPasswords($passwordIds, $message);
    }

    /**
     * Process folder deletion audit.
     * Note: The folder itself is already audited by DoctrineAuditListener.
     * We only audit the cascaded children.
     *
     * @param  ProcessDeletionAuditMessage  $message
     *
     * @return void
     * @throws DBALException
     */
    private function processFolderDeletion(ProcessDeletionAuditMessage $message): void
    {
        // Get all affected folder IDs (root + descendants) that were deleted in this operation
        $allFolderIds = $this->getAllDescendantFolderIds($message->entityId, $message);

        // Audit only descendant folders (exclude root folder)
        $descendantIds = array_diff($allFolderIds, [$message->entityId]);
        if (!empty($descendantIds)) {
            $this->auditFolders($descendantIds, $message);
        }

        // Get all passwords in affected folders that were deleted in this operation
        if (!empty($allFolderIds)) {
            $passwordIds = $this->db()->fetchFirstColumn(
                "SELECT id FROM passwords 
                 WHERE folder_id IN (:folderIds) 
                 AND deleted_by = :deletedBy
                 AND deleted_at = :deletedAt",
                [
                    'folderIds' => $allFolderIds,
                    'deletedBy' => $message->deletedBy,
                    'deletedAt' => $message->deletedAt->format("Y-m-d H:i:s"),
                ],
                [
                    'folderIds' => ArrayParameterType::STRING,
                ]
            );

            $this->auditPasswords($passwordIds, $message);
        }
    }

    /**
     * Get all descendant folder IDs for a given root folder.
     *
     * @param  string  $rootFolderId
     * @param  ProcessDeletionAuditMessage  $message
     *
     * @return string[]
     * @throws DBALException
     */
    private function getAllDescendantFolderIds(string $rootFolderId, ProcessDeletionAuditMessage $message): array
    {
        $allFolderIds = [$rootFolderId];
        $front = [$rootFolderId];

        while ($front) {
            $children = $this->db()->executeQuery(
                "SELECT id FROM folders 
                 WHERE parent_folder_id IN (:ids) 
                 AND deleted_by = :deletedBy
                 AND deleted_at = :deletedAt",
                [
                    'ids' => array_values($front),
                    'deletedBy' => $message->deletedBy,
                    'deletedAt' => $message->deletedAt->format("Y-m-d H:i:s"),
                ],
                [
                    'ids' => ArrayParameterType::STRING,
                ]
            )->fetchFirstColumn();

            $children = array_values(array_diff($children, $allFolderIds));
            if (empty($children)) {
                break;
            }

            $allFolderIds = array_merge($allFolderIds, $children);
            $front = $children;
        }

        return $allFolderIds;
    }

    /**
     * Audit multiple folders in batches.
     *
     * @param  string[]  $folderIds
     * @param  ProcessDeletionAuditMessage  $message
     *
     * @return void
     * @throws DBALException
     */
    private function auditFolders(
        array $folderIds,
        ProcessDeletionAuditMessage $message
    ): void {
        if (empty($folderIds)) {
            return;
        }

        foreach (array_chunk($folderIds, self::BATCH_SIZE) as $chunk) {
            $folders = $this->db()->executeQuery(
                "SELECT id, name, external_id, deleted_at, deleted_by
                 FROM folders
                 WHERE id IN (:ids)",
                ['ids' => $chunk],
                ['ids' => ArrayParameterType::STRING]
            )->fetchAllAssociative();

            foreach ($folders as $folder) {
                $this->queueAuditLog([
                    'id' => Uuid::v4()->toRfc4122(),
                    'action_type' => AuditAction::Updated->value,
                    'entity_id' => $folder['id'],
                    'entity_type' => Folder::class,
                    'old_values' => json_encode([
                        'deletedAt' => null,
                        'deletedBy' => null,
                    ]),
                    'new_values' => json_encode([
                        'deletedAt' => $folder['deleted_at'],
                        'deletedBy' => $folder['deleted_by'],
                    ]),
                    'metadata' => $this->buildFolderMetadata($folder),
                    'user_email' => $message->deletedBy,
                    'user_id' => $this->userId,
                    'ip_address' => $message->clientIpAddress,
                    'user_agent' => $message->userAgent,
                    'created_at' => $message->deletedAt->format("Y-m-d H:i:s"),
                ]);
            }
        }
    }

    /**
     * Audit multiple passwords in batches.
     *
     * @param  string[]  $passwordIds
     * @param  ProcessDeletionAuditMessage  $message
     *
     * @return void
     * @throws DBALException
     */
    private function auditPasswords(
        array $passwordIds,
        ProcessDeletionAuditMessage $message
    ): void {
        if (empty($passwordIds)) {
            return;
        }

        foreach (array_chunk($passwordIds, self::BATCH_SIZE) as $chunk) {
            $passwords = $this->db()->executeQuery(
                "SELECT id, title, external_id, target, deleted_at, deleted_by
                 FROM passwords
                 WHERE id IN (:ids)",
                ['ids' => $chunk],
                ['ids' => ArrayParameterType::STRING]
            )->fetchAllAssociative();

            foreach ($passwords as $password) {
                $this->queueAuditLog([
                    'id' => Uuid::v4()->toRfc4122(),
                    'action_type' => AuditAction::Updated->value,
                    'entity_id' => $password['id'],
                    'entity_type' => Password::class,
                    'old_values' => json_encode([
                        'deletedAt' => null,
                        'deletedBy' => null,
                    ]),
                    'new_values' => json_encode([
                        'deletedAt' => $password['deleted_at'],
                        'deletedBy' => $password['deleted_by'],
                    ]),
                    'metadata' => $this->buildPasswordMetadata($password),
                    'user_email' => $message->deletedBy,
                    'user_id' => $this->userId,
                    'ip_address' => $message->clientIpAddress,
                    'user_agent' => $message->userAgent,
                    'created_at' => $message->deletedAt->format("Y-m-d H:i:s"),
                ]);
            }
        }
    }

    /**
     * Build the metadata string for a folder.
     *
     * @param  array  $folder
     *
     * @return string
     */
    private function buildFolderMetadata(array $folder): string
    {
        $str = (string)($folder['name'] ?? '');

        if (!empty($folder['external_id'])) {
            $str .= " [{$folder['external_id']}]";
        }

        return $str;
    }

    /**
     * Build the metadata string for a password.
     *
     * @param  array  $password
     *
     * @return string
     */
    private function buildPasswordMetadata(array $password): string
    {
        $str = (string)($password['title'] ?? '');

        if (!empty($password['external_id'])) {
            $str .= " [{$password['external_id']}]";
        }

        if (!empty($password['target'])) {
            $str .= " ({$password['target']})";
        }

        return $str;
    }

    /**
     * Add an audit log entry to the queue.
     *
     * @param  array  $entry
     *
     * @return void
     */
    private function queueAuditLog(array $entry): void
    {
        $this->auditLogQueue[] = $entry;

        if (count($this->auditLogQueue) >= self::BULK_INSERT_SIZE) {
            $this->flushAuditLogs();
        }
    }

    /**
     * Flush audit log queue to the database.
     *
     * @return void
     */
    private function flushAuditLogs(): void
    {
        if (empty($this->auditLogQueue)) {
            return;
        }

        try {
            $placeholders = [];
            $params = [];
            $types = [];

            foreach ($this->auditLogQueue as $i => $entry) {
                $placeholders[] = sprintf(
                    "(:id_%d, :action_%d, :entity_id_%d, :entity_type_%d, :old_values_%d, :new_values_%d, :metadata_%d, :user_email_%d, :user_id_%d, :ip_%d, :ua_%d, :created_%d)",
                    $i,
                    $i,
                    $i,
                    $i,
                    $i,
                    $i,
                    $i,
                    $i,
                    $i,
                    $i,
                    $i,
                    $i
                );

                $params["id_$i"] = $entry['id'];
                $params["action_$i"] = $entry['action_type'];
                $params["entity_id_$i"] = $entry['entity_id'];
                $params["entity_type_$i"] = $entry['entity_type'];
                $params["old_values_$i"] = $entry['old_values'];
                $params["new_values_$i"] = $entry['new_values'];
                $params["metadata_$i"] = $entry['metadata'];
                $params["user_email_$i"] = $entry['user_email'];
                $params["user_id_$i"] = $entry['user_id'];
                $params["ip_$i"] = $entry['ip_address'];
                $params["ua_$i"] = $entry['user_agent'];
                $params["created_$i"] = $entry['created_at'];
            }

            $sql = "INSERT INTO audit_logs 
                    (
                        id,
                        action_type,
                        entity_id,
                        entity_type,
                        old_values,
                        new_values,
                        metadata,
                        user_email,
                        user_id,
                        ip_address,
                        user_agent,
                        created_at
                    ) VALUES " . implode(", ", $placeholders);

            $this->db()->executeStatement($sql, $params, $types);
            $this->auditLogQueue = [];
        } catch (DBALException) {
            $this->auditLogQueue = [];
        }
    }
}
