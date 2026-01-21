<?php

/**
 * @author bsteffan
 * @since 2025-06-10
 */

namespace App\Controller\Folder;

use App\Controller\Folder\Dto\CreateDto;
use App\Controller\GroupInheritanceTrait;
use App\Controller\GroupValidationTrait;
use App\Controller\NulledValueGetterTrait;
use App\Controller\PermissionProcessingTrait;
use App\Entity\Enums\FolderField;
use App\Entity\Folder;
use App\Entity\FoldersGroup;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\Vault;
use App\Normalizer\FolderNormalizer;
use App\Repository\FolderRepository;
use App\Repository\GroupRepository;
use App\Repository\VaultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CreateController extends AbstractController
{
    use GroupInheritanceTrait;
    use GroupValidationTrait;
    use NulledValueGetterTrait;
    use PermissionProcessingTrait;

    /**
     * Create a new Folder.
     *
     * @param  CreateDto  $createDto
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     */
    #[Route("/folders", name: "api_folders_create", methods: ["POST"])]
    public function index(
        #[MapRequestPayload] CreateDto $createDto,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();
        $groupIds = $loggedInUser->getGroupIds();
        $loggedInUserIdentifier = $loggedInUser->getUserIdentifier();

        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        $vaultId = $createDto->vaultId;
        $vault = $vaultRepository->findByIds(
            [$vaultId],
            [
                "PARTIAL v.{id, name, mandatoryFolderFields}",
                "PARTIAL gv.{group, vault, canWrite, partial}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            groupVaultAlias: "gv"
        )[$vaultId] ?? null;

        if (is_null($vault) || !$vault->hasReadPermission($groupIds)) {
            throw new BadRequestHttpException("Invalid vault ID.");
        }

        $explicitGroupsProvided = !empty($createDto->groups);
        $explicitUserPermissionsProvided = !empty($createDto->userPermissions);
        $explicitPermissionsProvided = $explicitGroupsProvided || $explicitUserPermissionsProvided;
        $parent = null;

        if ($vault->isPrivate() && $explicitGroupsProvided) {
            throw new BadRequestHttpException("Private vaults don't allow setting groups.");
        }

        if ($vault->isPrivate() && $explicitUserPermissionsProvided) {
            throw new BadRequestHttpException("Private vaults don't allow sharing with users.");
        }

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);

        // Process group permissions
        $groups = [];
        $groupPermissions = [];
        if ($explicitGroupsProvided) {
            $groupResult = $this->processGroupPermissions($createDto->groups, $groupRepository);
            $groups = $groupResult['groups'];
            $groupPermissions = $groupResult['groupPermissions'];
        }

        // Process user permissions (private groups)
        $userResult = $this->processUserPermissions($createDto->userPermissions, $groupRepository);
        $privateGroups = $userResult['privateGroups'];
        $userPermissions = $userResult['userPermissions'];

        if (!is_null($createDto->parentFolderId)) {
            /** @var FolderRepository $folderRepository */
            $folderRepository = $entityManager->getRepository(Folder::class);
            $parent = $folderRepository->findByIds(
                [$createDto->parentFolderId],
                [
                    "PARTIAL f.{id, name, externalId}",
                    "PARTIAL fg.{folder, group, canWrite, partial}",
                    "PARTIAL g.{id, name, private}",
                    "PARTIAL v.{id}",
                ],
                groupAlias: "g",
                folderGroupAlias: "fg",
                vaultAlias: "v"
            )[$createDto->parentFolderId] ?? null;

            if (is_null($parent) || !$parent->hasReadPermission($groupIds)) {
                throw new BadRequestHttpException("Invalid parent folder ID.");
            }

            if ($parent->getVault()->getId() !== $vault->getId()) {
                throw new BadRequestHttpException("Parent folder is not in the same vault.");
            }

            if (!$parent->hasWritePermission($groupIds)) {
                throw new BadRequestHttpException("You don't have permission to create a folder in this folder.");
            }
        } elseif (!$vault->hasWritePermission($groupIds)) {
            throw new BadRequestHttpException("You don't have permission to create a folder in this vault.");
        }

        // If no permissions are explicitly provided, we use the parent folder's groups or the vault's groups.
        if (!$explicitPermissionsProvided) {
            $groupPermissions = [];
            if (!is_null($parent)) {
                $source = array_filter(
                    $parent->getFolderGroups()->toArray(),
                    fn($fg) => !$fg->isPartial()
                );
                $groups = array_map(fn($fg) => $fg->getGroup(), $source);
                foreach ($source as $fg) {
                    $groupPermissions[$fg->getGroup()->getId()] = $fg->canWrite();
                }
            } else {
                $source = array_filter(
                    $vault->getGroupVaults()->toArray(),
                    fn($gv) => !$gv->isPartial()
                );
                $groups = array_map(fn($gv) => $gv->getGroup(), $source);
                foreach ($source as $gv) {
                    $groupPermissions[$gv->getGroup()->getId()] = $gv->canWrite();
                }
            }
        }
        $groupIds = array_map(fn($g) => $g->getId(), $groups);

        // Guard: at least one write-capable group or user must be present
        $hasWrite = array_any($groupPermissions, fn($canWrite) => $canWrite === true)
                    || array_any($userPermissions, fn($canWrite) => $canWrite === true);
        if (!$hasWrite) {
            throw new BadRequestHttpException("At least one group or user must have write access on the folder.");
        }

        $mandatoryFolderFields = $vault->getMandatoryFolderFields() ?? [];
        if (is_null($createDto->externalId) && in_array(FolderField::ExternalId, $mandatoryFolderFields)) {
            throw new BadRequestHttpException("External ID is mandatory for this vault.");
        }

        $newFolder = new Folder()->setName($createDto->name)
                                 ->setExternalId($this->getTrimmedOrNull($createDto->externalId))
                                 ->setIconName($createDto->iconName)
                                 ->setDescription($this->getTrimmedOrNull($createDto->description))
                                 ->setVault($vault)
                                 ->setCreatedBy($loggedInUserIdentifier);

        $entityManager->persist($newFolder);

        // Exclude private groups from $groups if they're already in $privateGroups (to avoid duplicates)
        $privateGroupIds = array_map(fn($g) => $g->getId(), $privateGroups);
        $groups = array_filter($groups, fn($g) => !in_array($g->getId(), $privateGroupIds));

        foreach ($groups as $group) {
            $permission = $groupPermissions[$group->getId()] ?? false;

            $folderGroup = new FoldersGroup()->setFolder($newFolder)
                                             ->setGroup($group)
                                             ->setCanWrite($permission)
                                             ->setPartial(false)
                                             ->setCreatedBy($loggedInUserIdentifier);

            $newFolder->addFolderGroup($folderGroup);
            $entityManager->persist($folderGroup);
        }

        // Create FoldersGroup entries for user permissions (private groups)
        foreach ($privateGroups as $group) {
            $permission = $userPermissions[$group->getId()] ?? false;

            $folderGroup = new FoldersGroup()->setFolder($newFolder)
                                             ->setGroup($group)
                                             ->setCanWrite($permission)
                                             ->setPartial(false)
                                             ->setCreatedBy($loggedInUserIdentifier);

            $newFolder->addFolderGroup($folderGroup);
            $entityManager->persist($folderGroup);
        }

        if (!is_null($parent)) {
            $newFolder->setParent($parent);

            // Check if the parent is missing groups if explicit permissions are provided.
            if ($explicitPermissionsProvided) {
                $parentGroupIds = $parent->getGroupIds();
                $allProvidedGroupIds = array_merge($groupIds, $privateGroupIds);
                $allProvidedGroups = array_merge($groups, $privateGroups);

                $extraGroupIds = array_diff($allProvidedGroupIds, $parentGroupIds);
                if (!empty($extraGroupIds)) {
                    $this->addMissingGroupsToParentFolder(
                        $parent,
                        $allProvidedGroups,
                        $loggedInUserIdentifier,
                        $entityManager
                    );
                }

                $excludedGroupIds = array_diff($parentGroupIds, $allProvidedGroupIds);
                if (!empty($excludedGroupIds)) {
                    $this->markParentFolderAsPartial(
                        $parent,
                        $excludedGroupIds,
                        $loggedInUserIdentifier,
                        $entityManager
                    );
                }
            }
        }

        if ($explicitPermissionsProvided) {
            $allProvidedGroupIds = array_merge($groupIds, $privateGroupIds);
            $allProvidedGroups = array_merge($groups, $privateGroups);

            $this->addMissingGroupsToVault($vault, $allProvidedGroups, $loggedInUserIdentifier, $entityManager);

            $excludedGroupIds = array_diff($vault->getGroupIds(), $allProvidedGroupIds);
            if (!empty($excludedGroupIds)) {
                $this->markVaultAsPartial(
                    $vault,
                    $excludedGroupIds,
                    $loggedInUserIdentifier,
                    $entityManager
                );
            }
        }

        $entityManager->flush();

        return $this->json($newFolder, 201, context: [FolderNormalizer::WITH_GROUPS]);
    }
}
