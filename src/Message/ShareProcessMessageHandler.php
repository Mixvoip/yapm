<?php

namespace App\Message;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Entity\Enums\AuditAction;
use App\Entity\Enums\ShareItem\Status as ItemStatus;
use App\Entity\Enums\ShareItem\TargetType as ItemTargetType;
use App\Entity\Enums\ShareProcess\Status as ProcessStatus;
use App\Entity\Enums\ShareProcess\TargetType;
use App\Entity\Enums\ShareProcess\TargetType as ProcessTargetType;
use App\Entity\Folder;
use App\Entity\Password;
use App\Service\Encryption\EncryptionService;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler]
class ShareProcessMessageHandler
{
    private const int BATCH_SIZE = 500;
    private const int BULK_INSERT_SIZE = 50; // For bulk inserts within a single operation

    private ?string $clientIp = null;
    private ?string $userAgent = null;

    /**
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptionService  $encryptionService
     * @param  MessageBusInterface  $messageBus
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    /**
     * @param  ShareProcessMessage  $message
     *
     * @return void
     * @throws DBALException
     */
    public function __invoke(ShareProcessMessage $message): void
    {
        $this->clientIp = $message->clientIpAddress;
        $this->userAgent = $message->userAgent;

        $proc = $this->loadProcessRow($message->processId);
        if (!$proc || $proc['status'] === ProcessStatus::Canceled->value) {
            return;
        }

        if ($proc['status'] === ProcessStatus::Pending->value) {
            $this->markProcessRunning($proc['id']);
            $proc['status'] = ProcessStatus::Running->value;
        }

        $user = $this->loadUserByEmail($proc['created_by']);
        if (empty($user)) {
            $this->failProcess($proc['id'], 'User not found');
            return;
        }

        try {
            $userGroupIds = $this->loadUserGroupIds($user['id']);
            $passwordIds = $this->findPasswordTargets($proc, $userGroupIds);
            $folderIds = $this->findFolderTargets($proc, $userGroupIds);
            $total = count($passwordIds) + count($folderIds);
            $this->setProcessTotals($proc['id'], $total);

            if ($total === 0) {
                $this->completeProcess($proc);
                return;
            }

            $this->createShareItems($proc['id'], $passwordIds, $folderIds);

            // Decrypt the user's private key ONCE per processing run
            $decryptedPrivateKey = $this->decryptUserPrivateKey($user, $message->encryptedClientData);

            $this->processShareItemsBulk($proc, $user, $decryptedPrivateKey);

            $this->completeProcess($proc);
        } catch (Throwable $e) {
            $this->failProcess($proc['id'], $e->getMessage());
        } finally {
            $this->encryptionService->secureMemzero($decryptedPrivateKey);
        }
    }

    /**
     * @return Connection
     */
    private function db(): Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * @return DateTimeImmutable
     */
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    /**
     * @param  string  $id
     *
     * @return array|null
     * @throws DBALException
     */
    private function loadProcessRow(string $id): ?array
    {
        $row = $this->db()->fetchAssociative(
            "SELECT id, status, created_by, requested_groups, target_type, scope_id, is_cascade
               FROM share_processes
              WHERE id = :id",
            ['id' => $id],
        );

        if (empty($row)) {
            return null;
        }

        $row['requested_groups'] = json_decode($row['requested_groups'], true);
        $row['is_cascade'] = (bool)$row['is_cascade'];

        return $row;
    }

    /**
     * @param  string  $email
     *
     * @return array|null
     * @throws DBALException
     */
    private function loadUserByEmail(string $email): ?array
    {
        return $this->db()->fetchAssociative(
            "SELECT id, email, password, encrypted_private_key, private_key_nonce, key_salt
               FROM users WHERE email = :email",
            ['email' => $email]
        );
    }

    /**
     * @param  string  $userId
     *
     * @return array
     * @throws DBALException
     */
    private function loadUserGroupIds(string $userId): array
    {
        return $this->db()->fetchFirstColumn(
            "SELECT gu.group_id 
                FROM groups_users gu
                INNER JOIN groups g on g.id = gu.group_id
                WHERE gu.user_id = :uid
                AND g.deleted_at IS NULL",
            ['uid' => $userId],
        );
    }

    /**
     * Process share items in optimized bulk operations.
     *
     * @param  array  $procRow
     * @param  array  $userRow
     * @param  string  $decryptedPrivateKey
     *
     * @return void
     * @throws DBALException
     */
    private function processShareItemsBulk(
        array $procRow,
        array $userRow,
        string $decryptedPrivateKey
    ): void {
        $processed = 0;
        $failed = 0;

        do {
            // Fetch batch of pending items
            $batchItems = $this->db()
                               ->executeQuery(
                                   "SELECT id, target_type, target_id
                                    FROM share_items
                                    WHERE process_id = :pid AND status = :status
                                    ORDER BY target_type, id
                                    LIMIT :limit",
                                   [
                                       'pid' => $procRow['id'],
                                       'status' => ItemStatus::Pending->value,
                                       'limit' => self::BATCH_SIZE,
                                   ],
                                   ['limit' => Types::INTEGER,]
                               )
                               ->fetchAllAssociative();

            if (empty($batchItems)) {
                break;
            }

            // Group by type for efficient processing
            $passwordItems = [];
            $folderItems = [];

            foreach ($batchItems as $item) {
                if ($item['target_type'] === ItemTargetType::Password->value) {
                    $passwordItems[] = $item;
                } else {
                    $folderItems[] = $item;
                }
            }

            // Process passwords in bulk
            if (!empty($passwordItems)) {
                $result = $this->processItems(
                    $passwordItems,
                    fn(array $passwordIds) => $this->bulkUpdatePasswordPermissions(
                        $passwordIds,
                        $procRow['requested_groups'],
                        $decryptedPrivateKey,
                        $userRow
                    )
                );
                $processed += $result['processed'];
                $failed += $result['failed'];
            }

            // Process folders in bulk
            if (!empty($folderItems)) {
                $result = $this->processItems(
                    $folderItems,
                    fn(array $folderIds) => $this->bulkUpdateFolderPermissions(
                        $folderIds,
                        $procRow['requested_groups'],
                        $userRow
                    )
                );
                $processed += $result['processed'];
                $failed += $result['failed'];
            }

            $this->setProcessProgress($procRow['id'], $processed, $failed);
        } while (count($batchItems) === self::BATCH_SIZE);
    }

    /**
     * Process a batch of items.
     *
     * @param  array  $items
     * @param  callable  $processItem
     *
     * @return int[]
     * @throws DBALException
     */
    #[ArrayShape(['processed' => "int", 'failed' => "int"])]
    private function processItems(
        array $items,
        callable $processItem
    ): array {
        $processed = 0;
        $failed = 0;
        $successItems = [];
        $failedItems = [];

        // Extract password IDs and group by decrypt path for efficiency
        $itemIds = array_column($items, 'target_id');
        $itemsByTargetId = [];
        foreach ($items as $item) {
            $itemsByTargetId[$item['target_id']] = $item;
        }

        try {
            $results = $processItem($itemIds);

            foreach ($results as $itemId => $success) {
                $item = $itemsByTargetId[$itemId];
                if ($success['success']) {
                    $successItems[] = $item['id'];
                    $processed++;
                } else {
                    $failedItems[] = [
                        'id' => $item['id'],
                        'message' => $success['error'] ?? "Permission update failed.",
                    ];
                    $failed++;
                }
            }
        } catch (Throwable $e) {
            // Mark all as failed
            foreach ($items as $item) {
                $failedItems[] = ['id' => $item['id'], 'message' => $e->getMessage()];
                $failed++;
            }
        }

        // Bulk update statuses
        if ($successItems) {
            $this->updateShareItemStatus($successItems, ItemStatus::Done);
        }
        if ($failedItems) {
            $this->updateShareItemStatusWithMessages($failedItems, ItemStatus::Failed);
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * Bulk update password permissions for multiple passwords
     *
     * @param  array  $passwordIds
     * @param  array  $requestedGroups
     * @param  string  $decryptedPrivateKey
     * @param  array  $userRow
     *
     * @return array
     * @throws DBALException
     */
    private function bulkUpdatePasswordPermissions(
        array $passwordIds,
        array $requestedGroups,
        string $decryptedPrivateKey,
        array $userRow
    ): array {
        if (empty($passwordIds)) {
            return [];
        }

        $requestedGroups = array_filter(
            $requestedGroups,
            fn($g) => ($g['partial'] === false) // drop partial=true
        );

        $results = [];
        $requestedIds = array_map(fn($g) => $g['groupId'], $requestedGroups);
        $permByGroup = [];
        foreach ($requestedGroups as $rg) {
            $permByGroup[$rg['groupId']] = (bool)($rg['canWrite'] ?? false);
        }

        // Load all current relations for all passwords at once
        $currentRelations = $this->db()
                                 ->executeQuery(
                                     "SELECT 
                                        gp.password_id,
                                        gp.group_id,
                                        gp.can_write,
                                        p.title,
                                        p.external_id,
                                        p.target,
                                        g.name AS group_name
                                        FROM groups_passwords gp
                                        INNER JOIN passwords p ON p.id = gp.password_id
                                        INNER JOIN groups g ON g.id = gp.group_id
                                        WHERE password_id IN (:pids)
                                        AND g.deleted_at IS NULL
                                        AND p.deleted_at IS NULL",
                                     ['pids' => $passwordIds],
                                     ['pids' => ArrayParameterType::STRING]
                                 )
                                 ->fetchAllAssociative();

        // Group current relations by password
        $currentByPassword = $this->mapPasswordRelations($currentRelations);
        $metaByPassword = $this->buildPasswordMetadata($currentRelations);
        $groupNames = $this->buildGroupNameMap($currentRelations, $requestedGroups);

        // Process each password
        foreach ($passwordIds as $passwordId) {
            try {
                $current = $currentByPassword[$passwordId] ?? [];
                $currentIds = array_keys($current);

                $toRemove = array_diff($currentIds, $requestedIds);
                $toAdd = array_diff($requestedIds, $currentIds);
                $toKeep = array_intersect($currentIds, $requestedIds);

                // Add new groups if needed
                if (!empty($toAdd)) {
                    $this->bulkAddPasswordGroups(
                        $passwordId,
                        array_values($toAdd),
                        $permByGroup,
                        $decryptedPrivateKey,
                        $userRow
                    );
                }
                // Bulk remove
                if (!empty($toRemove)) {
                    $this->db()->executeStatement(
                        "DELETE FROM groups_passwords
                             WHERE password_id = :pid AND group_id IN (:gids)",
                        ['pid' => $passwordId, 'gids' => array_values($toRemove)],
                        ['gids' => ArrayParameterType::STRING]
                    );
                }

                // Bulk update permissions for kept groups
                foreach ($toKeep as $gid) {
                    $newCW = $permByGroup[$gid] ?? $current[$gid];
                    if ($newCW !== $current[$gid]) {
                        $this->db()->executeStatement(
                            "UPDATE groups_passwords
                                 SET can_write = :canWrite
                                 WHERE password_id = :pid AND group_id = :gid",
                            ['canWrite' => $newCW, 'pid' => $passwordId, 'gid' => $gid],
                            ['canWrite' => Types::BOOLEAN]
                        );
                    }
                }

                $results[$passwordId]['success'] = true;
            } catch (Throwable $e) {
                $results[$passwordId]['success'] = false;
                $results[$passwordId]['error'] = $e->getMessage();
            }
        }

        $newRelations = $this->db()
                             ->executeQuery(
                                 "SELECT gp.password_id, gp.group_id, gp.can_write
                                    FROM groups_passwords gp
                                    INNER JOIN groups g ON g.id = gp.group_id
                                    WHERE password_id IN (:pids)
                                    AND g.deleted_at IS NULL",
                                 ['pids' => $passwordIds],
                                 ['pids' => ArrayParameterType::STRING]
                             )
                             ->fetchAllAssociative();

        $newByPassword = $this->mapPasswordRelations($newRelations);
        foreach ($passwordIds as $pid) {
            $old = $currentByPassword[$pid] ?? [];
            $new = $newByPassword[$pid] ?? [];

            $diff = $this->diffPasswordPermissions($old, $new, $groupNames);
            if (!empty($diff)) {
                $this->auditLog(
                    $pid,
                    Password::class,
                    $userRow,
                    $metaByPassword[$pid] ?? "",
                    $diff
                );
            }
        }

        return $results;
    }

    /**
     * Bulk add password group relationships.
     *
     * @param  string  $passwordId
     * @param  array  $groupIds
     * @param  array  $permByGroup
     * @param  string  $decryptedPrivateKey
     * @param  array  $userRow
     *
     * @return void
     * @throws DBALException
     * @throws RandomException
     * @throws Exception
     */
    private function bulkAddPasswordGroups(
        string $passwordId,
        array $groupIds,
        array $permByGroup,
        string $decryptedPrivateKey,
        array $userRow
    ): void {
        if (empty($groupIds)) {
            return;
        }

        $decryptPath = $this->findDecryptPath($passwordId, $userRow['id']);
        if (is_null($decryptPath)) {
            throw new Exception("No decryption data for password.");
        }

        $groupKey = $this->encryptionService->decryptGroupPrivateKey(
            $decryptPath['gu_encrypted'],
            $decryptPath['gu_nonce'],
            $decryptPath['gu_pub'],
            $decryptedPrivateKey
        );

        // Get password key for this password
        $passwordKey = $this->getPasswordKey($passwordId, $groupKey, $decryptPath['group_id']);

        try {
            // Load target group public keys
            $groups = $this->db()
                           ->executeQuery(
                               "SELECT id, public_key FROM groups WHERE id IN (:ids)",
                               ['ids' => $groupIds],
                               ['ids' => ArrayParameterType::STRING]
                           )
                           ->fetchAllKeyValue();

            // Process in chunks for bulk insert
            foreach (array_chunk($groupIds, self::BULK_INSERT_SIZE) as $chunk) {
                $placeholders = [];
                $params = [];
                $types = [];

                foreach ($chunk as $i => $gid) {
                    if (!isset($groups[$gid])) {
                        continue;
                    }

                    $keys = $this->encryptionService->encryptPasswordKeyForGroup($passwordKey, $groups[$gid]);

                    $placeholders[] = "(:pid_$i, :gid_$i, :nonce_$i, :enc_key_$i, :enc_pub_$i, :can_write_$i, :created_by_$i)";

                    $params["pid_$i"] = $passwordId;
                    $params["gid_$i"] = $gid;
                    $params["nonce_$i"] = $keys['nonce'];
                    $params["enc_key_$i"] = $keys['encryptedPasswordKey'];
                    $params["enc_pub_$i"] = $keys['encryptionPublicKey'];
                    $params["can_write_$i"] = (bool)($permByGroup[$gid] ?? false);
                    $params["created_by_$i"] = $userRow['email'];

                    $types["can_write_$i"] = Types::BOOLEAN;
                }

                if ($placeholders) {
                    $sql = 'INSERT INTO groups_passwords
                    (password_id, group_id, nonce, encrypted_password_key, encryption_public_key, can_write, created_by)
                            VALUES ' . implode(',', $placeholders);

                    $this->db()->executeStatement($sql, $params, $types);
                }
            }
        } finally {
            $this->encryptionService->secureMemzero($passwordKey);
        }
    }

    /**
     * Bulk update folder permissions for multiple folders
     *
     * @param  array  $folderIds
     * @param  array  $requestedGroups
     * @param  array  $userRow
     *
     * @return array
     * @throws DBALException
     */
    private function bulkUpdateFolderPermissions(
        array $folderIds,
        array $requestedGroups,
        array $userRow
    ): array {
        if (empty($folderIds)) {
            return [];
        }

        $results = [];
        $requestedIds = array_map(fn($g) => $g['groupId'], $requestedGroups);

        $permByGroup = [];
        $partialByGroup = [];
        foreach ($requestedGroups as $requestedGroup) {
            $gid = $requestedGroup['groupId'];
            $permByGroup[$gid] = (bool)($requestedGroup['canWrite'] ?? false);
            $partialByGroup[$gid] = (bool)($requestedGroup['partial'] ?? false);
        }

        // Load all current relations
        $currentRelations = $this->db()
                                 ->executeQuery(
                                     "SELECT 
                                        fg.folder_id,
                                        fg.group_id,
                                        fg.can_write,
                                        fg.partial,
                                        f.name AS folder_name,
                                        f.external_id,
                                        g.name AS group_name
                                        FROM folders_groups fg
                                        INNER JOIN folders f ON f.id = fg.folder_id
                                        INNER JOIN groups g ON g.id = fg.group_id
                                        WHERE folder_id IN (:fids)
                                        AND g.deleted_at IS NULL
                                        AND f.deleted_at IS NULL",
                                     ['fids' => $folderIds],
                                     ['fids' => ArrayParameterType::STRING]
                                 )
                                 ->fetchAllAssociative();

        // Group by folder
        $currentByFolder = $this->mapFolderRelations($currentRelations);
        $metaByFolder = $this->buildFolderMetadata($currentRelations);
        $groupNames = $this->buildGroupNameMap($currentRelations, $requestedGroups);

        // Determine partial status for new rows based on actor's explicit access per folder
        $partialStatusByFolder = $this->determinePartialStatusForFolders($folderIds, $userRow['id']);

        foreach ($folderIds as $folderId) {
            try {
                $current = $currentByFolder[$folderId] ?? [];
                $currentIds = array_keys($current);
                $newRowPartial = $partialStatusByFolder[$folderId] ?? true;

                $toDemote = array_filter(
                    array_diff($currentIds, $requestedIds),
                    fn($gid) => !(
                        ($current[$gid]['partial'] ?? false) === true
                        && ($current[$gid]['can_write'] ?? false) === false
                    ),
                );

                $toAdd = array_diff($requestedIds, $currentIds);
                $toKeep = array_intersect($currentIds, $requestedIds);

                // Bulk operations per folder
                if (!empty($toDemote)) {
                    $this->db()
                         ->executeStatement(
                             "UPDATE folders_groups
                                SET partial = 1, can_write = 0, updated_by = :updatedBy
                                WHERE folder_id = :fid AND group_id IN (:gids)",
                             ['fid' => $folderId, 'gids' => $toDemote, 'updatedBy' => $userRow['email']],
                             ['gids' => ArrayParameterType::STRING]
                         );
                }

                // Update kept groups
                foreach ($toKeep as $gid) {
                    $newCW = $permByGroup[$gid] ?? $current[$gid]['can_write'];
                    $requestedPartial = $partialByGroup[$gid] ?? false;
                    if ($requestedPartial) {
                        continue;
                    }
                    $newPartial = ($partialByGroup[$gid] ?? false) || $newRowPartial;

                    if ($newCW !== $current[$gid]['can_write'] || $newPartial !== $current[$gid]['partial']) {
                        $this->db()->executeStatement(
                            "UPDATE folders_groups
                             SET can_write = :canWrite, partial = :partial, updated_by = :updatedBy
                             WHERE folder_id = :fid AND group_id = :gid",
                            [
                                'canWrite' => $newCW,
                                'partial' => $newPartial,  // keep/upgrade to explicit
                                'updatedBy' => $userRow['email'],
                                'fid' => $folderId,
                                'gid' => $gid,
                            ],
                            [
                                'canWrite' => Types::BOOLEAN,
                                'partial' => Types::BOOLEAN,
                            ]
                        );
                    }
                }

                $toAddFiltered = array_filter($toAdd, fn($gid) => !($partialByGroup[$gid] ?? false));
                // Add new groups with proper partial status - pass on the actor's partial state
                if (!empty($toAddFiltered)) {
                    $this->bulkAddFolderGroups(
                        $folderId,
                        array_values($toAddFiltered),
                        $permByGroup,
                        $userRow['email'],
                        $newRowPartial,
                        $partialByGroup
                    );
                }

                $results[$folderId]['success'] = true;
            } catch (Throwable $e) {
                $results[$folderId]['success'] = false;
                $results[$folderId]['error'] = $e->getMessage();
            }
        }

        $newRelations = $this->db()
                             ->executeQuery(
                                 "SELECT fg.folder_id, fg.group_id, fg.can_write, fg.partial
                                    FROM folders_groups fg
                                    INNER JOIN groups g ON g.id = fg.group_id
                                    WHERE folder_id IN (:fids)
                                    AND g.deleted_at IS NULL",
                                 ['fids' => $folderIds],
                                 ['fids' => ArrayParameterType::STRING]
                             )
                             ->fetchAllAssociative();

        $newByFolder = $this->mapFolderRelations($newRelations);

        // 4) diff + audit per folder
        foreach ($folderIds as $fid) {
            $old = $currentByFolder[$fid] ?? [];
            $new = $newByFolder[$fid] ?? [];

            $diff = $this->diffFolderPermissions($old, $new, $groupNames);
            if (!empty($diff)) {
                $this->auditLog(
                    $fid,
                    Folder::class,
                    $userRow,
                    $metaByFolder[$fid] ?? "",
                    $diff
                );
            }
        }

        return $results;
    }

    /**
     * Determine partial status for new rows based on actor's explicit access.
     *
     * @param  array  $folderIds
     * @param  string  $actorUserId
     *
     * @return array
     * @throws DBALException
     */
    private function determinePartialStatusForFolders(array $folderIds, string $actorUserId): array
    {
        if (empty($folderIds)) {
            return [];
        }

        $result = [];

        // Check actor's explicit write access for all folders at once
        $explicitAccess = $this->db()
                               ->executeQuery(
                                   "SELECT DISTINCT fg.folder_id
                                        FROM folders_groups fg
                                        JOIN groups_users gu ON gu.group_id = fg.group_id
                                        INNER JOIN groups g on g.id = gu.group_id
                                        WHERE fg.folder_id IN (:fids)
                                        AND gu.user_id = :uid
                                        AND fg.can_write = 1
                                        AND fg.partial = 0
                                        AND g.deleted_at IS NULL",
                                   [
                                       'fids' => $folderIds,
                                       'uid' => $actorUserId,
                                   ],
                                   ['fids' => ArrayParameterType::STRING,]
                               )
                               ->fetchFirstColumn();

        // Set partial status: false if actor has explicit write, true otherwise
        foreach ($folderIds as $folderId) {
            $actorHasExplicitWrite = in_array($folderId, $explicitAccess);
            $result[$folderId] = !$actorHasExplicitWrite;
        }

        return $result;
    }

    /**
     * Bulk add folder group relationships with partial status
     *
     * @param  string  $folderId
     * @param  array  $groupIds
     * @param  array  $permByGroup
     * @param  string  $userEmail
     * @param  bool  $newRowPartial
     * @param  array  $partialByGroup
     *
     * @return void
     * @throws DBALException
     */
    private function bulkAddFolderGroups(
        string $folderId,
        array $groupIds,
        array $permByGroup,
        string $userEmail,
        bool $newRowPartial = false,
        array $partialByGroup = []
    ): void {
        if (empty($groupIds)) {
            return;
        }

        // Process in chunks
        foreach (array_chunk($groupIds, self::BULK_INSERT_SIZE) as $chunk) {
            $placeholders = [];
            $params = [];
            $types = [];

            foreach ($chunk as $i => $gid) {
                $placeholders[] = "(:fid_$i, :gid_$i, :can_write_$i, :partial_$i, :created_by_$i)";

                $effectivePartial = ($partialByGroup[$gid] ?? false) || $newRowPartial;

                $params["fid_$i"] = $folderId;
                $params["gid_$i"] = $gid;
                $params["can_write_$i"] = (bool)($permByGroup[$gid] ?? false);
                $params["partial_$i"] = $effectivePartial;  // ← use the calculated partial status
                $params["created_by_$i"] = $userEmail;

                $types["can_write_$i"] = Types::BOOLEAN;
                $types["partial_$i"] = Types::BOOLEAN;
            }

            if ($placeholders) {
                $sql = "INSERT INTO folders_groups
                        (folder_id, group_id, can_write, partial, created_by)
                        VALUES " . implode(',', $placeholders);

                $this->db()->executeStatement($sql, $params, $types);
            }
        }
    }

    /**
     * Bulk update share item statuses.
     *
     * @param  array  $itemIds
     * @param  ItemStatus  $status
     *
     * @return void
     * @throws DBALException
     */
    private function updateShareItemStatus(array $itemIds, ItemStatus $status): void
    {
        if (empty($itemIds)) {
            return;
        }

        // Process in chunks to avoid query size limits
        foreach (array_chunk($itemIds, self::BULK_INSERT_SIZE) as $chunk) {
            $this->db()->executeStatement(
                "UPDATE share_items
                 SET status = :status, processed_at = :processedAt
                 WHERE id IN (:ids)",
                [
                    'status' => $status->value,
                    'processedAt' => $this->now(),
                    'ids' => array_values($chunk),
                ],
                [
                    'processedAt' => Types::DATETIME_IMMUTABLE,
                    'ids' => ArrayParameterType::STRING,
                ]
            );
        }
    }

    /**
     * Bulk update share item statuses with individual messages.
     *
     * @param  array  $items
     * @param  ItemStatus  $status
     *
     * @return void
     * @throws DBALException
     */
    private function updateShareItemStatusWithMessages(array $items, ItemStatus $status): void
    {
        if (empty($items)) {
            return;
        }

        foreach (array_chunk($items, self::BULK_INSERT_SIZE) as $chunk) {
            foreach ($chunk as $item) {
                $msg = $item['message'] ?? null;

                $this->db()->executeStatement(
                    "UPDATE share_items
                     SET status = :status, message = :message, processed_at = :processedAt
                     WHERE id = :id",
                    [
                        'status' => $status->value,
                        'message' => $msg,
                        'processedAt' => $this->now(),
                        'id' => $item['id'],
                    ],
                    ['processedAt' => Types::DATETIME_IMMUTABLE]
                );
            }
        }
    }

    /**
     * Find the decrypt path for a given password and user.
     *
     * @param  string  $passwordId
     * @param  string  $userId
     *
     * @return array|null
     * @throws DBALException
     */
    private function findDecryptPath(string $passwordId, string $userId): ?array
    {
        return $this->db()->fetchAssociative(
            "SELECT gp.group_id,
                    gp.encrypted_password_key AS gp_encrypted,
                    gp.nonce AS gp_nonce,
                    gp.encryption_public_key AS gp_pub,
                    gu.encrypted_group_private_key AS gu_encrypted,
                    gu.group_private_key_nonce AS gu_nonce,
                    gu.encryption_public_key AS gu_pub
             FROM groups_passwords gp
             JOIN groups_users gu ON gu.group_id = gp.group_id
             INNER JOIN groups g ON g.id = gp.group_id
             WHERE gp.password_id = :pid AND gu.user_id = :uid
             AND g.deleted_at IS NULL
             LIMIT 1",
            ['pid' => $passwordId, 'uid' => $userId]
        );
    }

    /**
     * Get the password key for a given password and group.
     *
     * @param  string  $passwordId
     * @param  string  $groupKey
     * @param  string  $groupId
     *
     * @return string
     * @throws DBALException
     */
    private function getPasswordKey(string $passwordId, string $groupKey, string $groupId): string
    {
        $decryptInfo = $this->db()->fetchAssociative(
            "SELECT encrypted_password_key, nonce, encryption_public_key
             FROM groups_passwords
             WHERE password_id = :pid AND group_id = :gid",
            ['pid' => $passwordId, 'gid' => $groupId],
        );

        if (!$decryptInfo) {
            throw new RuntimeException("No decrypt info for password $passwordId");
        }

        return $this->encryptionService->decryptPasswordKey(
            $decryptInfo['encrypted_password_key'],
            $decryptInfo['nonce'],
            $decryptInfo['encryption_public_key'],
            $groupKey
        );
    }

    /**
     * @param  string  $processId
     * @param  array  $passwordIds
     * @param  array  $folderIds
     *
     * @return void
     * @throws DBALException
     */
    private function createShareItems(string $processId, array $passwordIds, array $folderIds): void
    {
        if (empty($passwordIds) && empty($folderIds)) {
            return;
        }

        $byType = [
            ItemTargetType::Password->value => $passwordIds,
            ItemTargetType::Folder->value => $folderIds,
        ];

        foreach ($byType as $type => $ids) {
            $this->insertShareItemsForType($processId, $type, $ids);
        }
    }

    /**
     * Insert share items for a given type.
     *
     * @param  string  $processId
     * @param  string  $type
     * @param  array  $ids
     *
     * @return void
     * @throws DBALException
     */
    private function insertShareItemsForType(string $processId, string $type, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        foreach (array_chunk($ids, self::BULK_INSERT_SIZE) as $chunk) {
            $items = [];
            foreach ($chunk as $id) {
                $items[] = [
                    'id' => Uuid::v4()->toRfc4122(),
                    'process_id' => $processId,
                    'target_type' => $type,
                    'target_id' => $id,
                    'status' => ItemStatus::Pending->value,
                ];
            }

            $this->bulkInsertShareItems($items);
        }
    }

    /**
     * Optimized bulk insert for share items
     *
     * @param  array  $items
     *
     * @return void
     * @throws DBALException
     */
    private function bulkInsertShareItems(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $placeholders = [];
        $params = [];
        $types = [];

        foreach ($items as $i => $item) {
            $placeholders[] = "(:id_$i, :pid_$i, :type_$i, :tid_$i, :status_$i)";

            $params["id_$i"] = $item['id'];
            $params["pid_$i"] = $item['process_id'];
            $params["type_$i"] = $item['target_type'];
            $params["tid_$i"] = $item['target_id'];
            $params["status_$i"] = $item['status'];
        }

        $sql = 'INSERT INTO share_items 
                (id, process_id, target_type, target_id, status)
                VALUES ' . implode(',', $placeholders);

        $this->db()->executeStatement($sql, $params, $types);
    }

    /**
     * Decrypt the private key of a user.
     *
     * @param  array  $userRow
     * @param  EncryptedClientDataDto  $encryptedPassword
     *
     * @return string
     */
    private function decryptUserPrivateKey(
        array $userRow,
        EncryptedClientDataDto $encryptedPassword
    ): string {
        $plainTextPassword = $this->encryptionService->decryptFromClient(
            $encryptedPassword->encryptedData,
            $encryptedPassword->clientPublicKey,
            $encryptedPassword->nonce
        );

        $decryptedPrivateKey = $this->encryptionService->decryptUserPrivateKey(
            $plainTextPassword,
            $userRow['encrypted_private_key'],
            $userRow['private_key_nonce'],
            $userRow['key_salt']
        );

        $this->encryptionService->secureMemzero($plainTextPassword);
        return $decryptedPrivateKey;
    }

    /**
     * @param  string  $processId
     *
     * @return void
     * @throws DBALException
     */
    private function markProcessRunning(string $processId): void
    {
        $this->db()->executeStatement(
            "UPDATE share_processes
                SET status = :status, started_at = :startedAt
              WHERE id = :id AND status = :pending",
            [
                'status' => ProcessStatus::Running->value,
                'startedAt' => $this->now(),
                'id' => $processId,
                'pending' => ProcessStatus::Pending->value,
            ],
            ['startedAt' => Types::DATETIME_IMMUTABLE]
        );
    }

    /**
     * @param  array  $process
     *
     * @return void
     * @throws DBALException
     * @throws ExceptionInterface
     */
    private function completeProcess(array $process): void
    {
        $this->db()->executeStatement(
            "UPDATE share_processes
                SET status = :status, finished_at = :finishedAt
              WHERE id = :id",
            [
                'status' => ProcessStatus::Completed->value,
                'finishedAt' => $this->now(),
                'id' => $process['id'],
            ],
            ['finishedAt' => Types::DATETIME_IMMUTABLE]
        );

        if ($process['target_type'] === TargetType::Vault) {
            $vaultId = $process['scope_id'];
        } else {
            $vaultId = $this->db()->fetchOne(
                "SELECT vault_id FROM folders WHERE id = :fid",
                ['fid' => $process['scope_id']]
            );

            if ($vaultId === false) {
                $vaultId = null;
            }
        }

        $this->messageBus->dispatch(new PartialAccessCleanUpMessage($vaultId));
    }

    /**
     * @param  string  $processId
     * @param  string  $message
     *
     * @return void
     * @throws DBALException
     */
    private function failProcess(string $processId, string $message): void
    {
        $this->db()->executeStatement(
            "UPDATE share_processes
                SET status = :status, finished_at = :finishedAt, message = :message
              WHERE id = :id",
            [
                'status' => ProcessStatus::Failed->value,
                'finishedAt' => $this->now(),
                'message' => $message,
                'id' => $processId,
            ],
            ['finishedAt' => Types::DATETIME_IMMUTABLE]
        );
    }

    /**
     * @param  string  $processId
     * @param  int  $total
     *
     * @return void
     * @throws DBALException
     */
    private function setProcessTotals(string $processId, int $total): void
    {
        $this->db()->executeStatement(
            "UPDATE share_processes
                SET total_items = :totalItems
              WHERE id = :id",
            [
                'totalItems' => $total,
                'id' => $processId,
            ],
            ['totalItems' => Types::INTEGER]
        );
    }

    /**
     * @param  string  $processId
     * @param  int  $processed
     * @param  int  $failed
     *
     * @return void
     * @throws DBALException
     */
    private function setProcessProgress(string $processId, int $processed, int $failed): void
    {
        $this->db()->executeStatement(
            "UPDATE share_processes
                SET processed_items = :processed, failed_items = :failed
                WHERE id = :id",
            [
                'processed' => $processed,
                'failed' => $failed,
                'id' => $processId,
            ],
            [
                'processed' => Types::INTEGER,
                'failed' => Types::INTEGER,
            ]
        );
    }

    /**
     * @param  string  $rootFolderId
     *
     * @return string[]
     * @throws DBALException
     */
    private function computeFolderScopeIds(string $rootFolderId): array
    {
        $all = [$rootFolderId];
        $front = [$rootFolderId];

        while ($front) {
            $children = $this->db()
                             ->executeQuery(
                                 "SELECT id FROM folders WHERE parent_folder_id IN (:ids) AND deleted_at IS NULL",
                                 ['ids' => array_values($front)],
                                 ['ids' => ArrayParameterType::STRING]
                             )
                             ->fetchFirstColumn();

            $children = array_values(array_diff($children, $all));
            if (empty($children)) {
                break;
            }

            $all = array_merge($all, $children);
            $front = $children;
        }

        return $all; // root + descendants
    }

    /**
     * @param  array  $proc
     * @param  array  $groupIds
     *
     * @return array
     * @throws DBALException
     */
    private function findPasswordTargets(array $proc, array $groupIds): array
    {
        if (!$groupIds) {
            return [];
        }

        $sql = "SELECT DISTINCT gp.password_id
                  FROM groups_passwords gp
                  INNER JOIN passwords p ON p.id = gp.password_id
                 WHERE gp.group_id IN (:groupIds)
                   AND gp.can_write = :canWrite
                   AND p.deleted_at IS NULL";

        $params = ['groupIds' => $groupIds, 'canWrite' => true];
        $types = ['groupIds' => ArrayParameterType::STRING, 'canWrite' => Types::BOOLEAN];

        if ($proc['target_type'] === ProcessTargetType::Vault->value) {
            $sql .= " AND p.vault_id = :vaultId";
            $params['vaultId'] = $proc['scope_id'];

            if (!$proc['is_cascade']) {
                $sql .= " AND p.folder_id IS NULL";
            }
        } elseif ($proc['is_cascade']) {
            // Folder
            $folderIds = $this->computeFolderScopeIds($proc['scope_id']);
            if (!$folderIds) {
                return [];
            }
            $sql .= " AND p.folder_id IN (:folderIds)";
            $params['folderIds'] = $folderIds;
            $types['folderIds'] = ArrayParameterType::STRING;
        } else {
            $sql .= " AND p.folder_id = :folderId";
            $params['folderId'] = $proc['scope_id'];
        }

        return $this->db()
                    ->executeQuery($sql, $params, $types)
                    ->fetchFirstColumn();
    }

    /**
     * @param  array  $proc
     * @param  array  $groupIds
     *
     * @return array
     * @throws DBALException
     */
    private function findFolderTargets(array $proc, array $groupIds): array
    {
        if (!$proc['is_cascade'] || !$groupIds) {
            return [];
        }

        $sql = "SELECT DISTINCT f.id
                  FROM folders f
                  INNER JOIN folders_groups fg ON fg.folder_id = f.id
                 WHERE fg.group_id IN (:groupIds)
                   AND fg.can_write = :canWrite
                   AND f.deleted_at IS NULL";

        $params = ['groupIds' => $groupIds, 'canWrite' => true];
        $types = ['groupIds' => ArrayParameterType::STRING, 'canWrite' => Types::BOOLEAN];

        if ($proc['target_type'] === ProcessTargetType::Vault->value) {
            $sql .= " AND f.vault_id = :vaultId";
            $params['vaultId'] = $proc['scope_id'];
        } else { // Folder (descendants only; exclude the root itself)
            $all = $this->computeFolderScopeIds($proc['scope_id']);
            $subFolders = array_values(array_diff($all, [$proc['scope_id']]));
            if (!$subFolders) {
                return [];
            }
            $sql .= " AND f.id IN (:folderIds)";
            $params['folderIds'] = $subFolders;
            $types['folderIds'] = ArrayParameterType::STRING;
        }

        return $this->db()
                    ->executeQuery($sql, $params, $types)
                    ->fetchFirstColumn();
    }

    /**
     * Audit log for a share process.
     *
     * @param  string  $entityId
     * @param  string  $entityType
     * @param  array  $user
     * @param  string  $metadata
     * @param  array  $newValues
     *
     * @return void
     */
    private function auditLog(
        string $entityId,
        string $entityType,
        array $user,
        string $metadata,
        array $newValues
    ): void {
        try {
            $this->db()->insert(
                'audit_logs',
                [
                    'id' => Uuid::v4()->toRfc4122(),
                    'action_type' => AuditAction::Updated->value,
                    'entity_id' => $entityId,
                    'entity_type' => $entityType,
                    'new_values' => $newValues,
                    'user_email' => $user['email'],
                    'user_id' => $user['id'],
                    'metadata' => $metadata,
                    'ip_address' => $this->clientIp,
                    'user_agent' => $this->userAgent,
                ],
                ['new_values' => Types::JSON]
            );
        } catch (DBALException) {
            // Ignore, we don't want audit logging errors to fail the process
        }
    }

    /**
     * Map password relations.
     *
     * @param  array  $rows
     *
     * @return array
     */
    private function mapPasswordRelations(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['password_id']][$row['group_id']] = (bool)$row['can_write'];
        }
        return $map;
    }

    /**
     * Map folder relations.
     *
     * @param  array  $rows
     *
     * @return array
     */
    private function mapFolderRelations(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['folder_id']][$row['group_id']] = [
                'can_write' => (bool)$row['can_write'],
                'partial' => (bool)$row['partial'],
            ];
        }
        return $map;
    }

    /**
     * Show the differences between the old and new password permissions.
     *
     * @param  array  $oldPermissions
     * @param  array  $newPermissions
     * @param  array  $groupNames
     *
     * @return array
     */
    private function diffPasswordPermissions(array $oldPermissions, array $newPermissions, array $groupNames): array
    {
        $diff = [
            'permissions' => [
                'add' => [],
                'remove' => [],
                'update' => [],
            ],
        ];

        $all = array_unique(array_merge(array_keys($oldPermissions), array_keys($newPermissions)));
        foreach ($all as $gid) {
            $oldPermission = $oldPermissions[$gid] ?? null; // bool|null
            $newPermission = $newPermissions[$gid] ?? null; // bool|null

            // derive labels
            $groupName = $groupNames[$gid] ?? $gid;
            $oldAccess = is_null($oldPermission) ? null : ($oldPermission ? 'write' : 'read');
            $newAccess = is_null($newPermission) ? null : ($newPermission ? 'write' : 'read');

            if (is_null($oldPermission) && !is_null($newPermission)) {
                // added relation
                $diff['permissions']['add'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'access' => $newAccess,
                ];
            } elseif (!is_null($oldPermission) && is_null($newPermission)) {
                // removed relation
                $diff['permissions']['remove'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'access' => $oldAccess,
                ];
            } elseif (!is_null($oldPermission) && !is_null($newPermission) && $oldPermission !== $newPermission) {
                // access changed
                $diff['permissions']['update'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'from' => ['access' => $oldAccess],
                    'to' => ['access' => $newAccess],
                ];
            }
        }

        // If all three arrays are empty, return [] so callers can skip logging.
        if (
            empty($diff['permissions']['add'])
            && empty($diff['permissions']['remove'])
            && empty($diff['permissions']['update'])
        ) {
            return [];
        }

        return $diff;
    }

    /**
     * Show the differences between the old and new folder permissions.
     *
     * @param  array  $oldPermissions
     * @param  array  $newPermissions
     * @param  array  $groupNames
     *
     * @return array
     */
    private function diffFolderPermissions(array $oldPermissions, array $newPermissions, array $groupNames): array
    {
        $diff = [
            'permissions' => [
                'add' => [],
                'remove' => [],
                'update' => [],
            ],
        ];

        $all = array_unique(array_merge(array_keys($oldPermissions), array_keys($newPermissions)));
        foreach ($all as $gid) {
            $oldPermission = $oldPermissions[$gid] ?? null;
            $ewPermission = $newPermissions[$gid] ?? null;

            $groupName = $groupNames[$gid] ?? $gid;

            $oldAccess = is_null($oldPermission) ? null : ($oldPermission['can_write'] ? 'write' : 'read');
            $newAccess = is_null($ewPermission) ? null : ($ewPermission['can_write'] ? 'write' : 'read');
            $oldPartial = $oldPermission['partial'] ?? null;
            $newPartial = $ewPermission['partial'] ?? null;

            if (is_null($oldPermission) && !is_null($ewPermission)) {
                $diff['permissions']['add'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'access' => $newAccess,
                    'partial' => $newPartial,
                ];
            } elseif (!is_null($oldPermission) && is_null($ewPermission)) {
                $diff['permissions']['remove'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'access' => $oldAccess,
                    'partial' => $oldPartial,
                ];
            } elseif (
                !is_null($oldPermission)
                && !is_null($ewPermission)
                && ($oldAccess !== $newAccess || $oldPartial !== $newPartial)
            ) {
                $diff['permissions']['update'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'from' => ['access' => $oldAccess, 'partial' => $oldPartial],
                    'to' => ['access' => $newAccess, 'partial' => $newPartial],
                ];
            }
        }

        if (
            empty($diff['permissions']['add'])
            && empty($diff['permissions']['remove'])
            && empty($diff['permissions']['update'])
        ) {
            return [];
        }

        return $diff;
    }

    /**
     * Build the metadata string for a password.
     *
     * @param  array  $rows
     *
     * @return array
     */
    private function buildPasswordMetadata(array $rows): array
    {
        $meta = [];
        foreach ($rows as $row) {
            $pid = $row['password_id'];
            if (!isset($meta[$pid])) {
                $str = (string)($row['title'] ?? '');
                if (!empty($row['external_id'])) {
                    $str .= " [{$row['external_id']}]";
                }
                if (!empty($row['target'])) {
                    $str .= " ({$row['target']})";
                }
                $meta[$pid] = $str;
            }
        }
        return $meta;
    }

    /**
     * Build the metadata string for a folder.
     *
     * @param  array  $rows
     *
     * @return array
     */
    private function buildFolderMetadata(array $rows): array
    {
        $meta = [];
        foreach ($rows as $row) {
            $fid = $row['folder_id'];
            if (!isset($meta[$fid])) {
                $str = (string)($row['folder_name'] ?? '');
                if (!empty($row['external_id'])) {
                    $str .= " [{$row['external_id']}]";
                }
                $meta[$fid] = $str;
            }
        }
        return $meta;
    }

    /**
     * Build the group names map from current and requested groups.
     *
     * @param  array  $currentRows
     * @param  array  $requestedGroups
     *
     * @return array
     */
    private function buildGroupNameMap(array $currentRows, array $requestedGroups): array
    {
        $names = [];
        foreach ($currentRows as $row) {
            if (!empty($row['group_id'])) {
                $names[$row['group_id']] = $row['group_name'] ?? ($names[$row['group_id']] ?? null);
            }
        }
        foreach ($requestedGroups as $rg) {
            $gid = $rg['groupId'];
            // accept either 'name' or 'groupName' depending on your payload
            $names[$gid] = $rg['name'] ?? $rg['groupName'] ?? ($names[$gid] ?? $gid);
        }
        // remove nulls if any
        return array_filter($names, fn($v) => $v !== null);
    }
}
