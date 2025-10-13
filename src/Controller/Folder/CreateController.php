<?php

/**
 * @author bsteffan
 * @since 2025-06-10
 */

namespace App\Controller\Folder;

use App\Controller\Dto\GroupPermissionDto;
use App\Controller\Folder\Dto\CreateDto;
use App\Controller\GroupInheritanceTrait;
use App\Controller\GroupValidationTrait;
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
        $vaultId = $createDto->getVaultId();
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

        $requestedGroupIds = array_map(fn(GroupPermissionDto $g) => $g->groupId, $createDto->getGroups());
        $this->assertNoDuplicateGroupIds($requestedGroupIds);

        $groups = [];
        $explicitGroupsProvided = !empty($requestedGroupIds);
        $parent = null;

        if ($vault->isPrivate() && $explicitGroupsProvided) {
            throw new BadRequestHttpException("Private vaults don't allow setting groups.");
        }

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);

        if ($explicitGroupsProvided) {
            $groups = $groupRepository->findByIds(
                $requestedGroupIds,
                ["PARTIAL g.{id, name, private}"]
            );
            $this->assertGroupsExist($requestedGroupIds, $groups);
            $this->assertNoPrivateGroups($groups);

            $groupPermissions = [];
            foreach ($createDto->getGroups() as $groupDto) {
                $groupPermissions[$groupDto->groupId] = $groupDto->canWrite;
            }
        }

        if (!is_null($createDto->getParentFolderId())) {
            /** @var FolderRepository $folderRepository */
            $folderRepository = $entityManager->getRepository(Folder::class);
            $parent = $folderRepository->findByIds(
                [$createDto->getParentFolderId()],
                [
                    "PARTIAL f.{id, name, externalId}",
                    "PARTIAL fg.{folder, group, canWrite, partial}",
                    "PARTIAL g.{id, name, private}",
                    "PARTIAL v.{id}",
                ],
                groupAlias: "g",
                folderGroupAlias: "fg",
                vaultAlias: "v"
            )[$createDto->getParentFolderId()] ?? null;

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

        // If no groups are explicitly provided, we use the parent folder's groups or the vault's groups.
        if (!$explicitGroupsProvided) {
            $groupPermissions = [];
            if ($parent) {
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

        // Guard: at least one write-capable group must be present
        $hasWrite = false;
        foreach ($groups as $g) {
            if (($groupPermissions[$g->getId()] ?? false) === true) {
                $hasWrite = true;
                break;
            }
        }

        if (!$hasWrite) {
            throw new BadRequestHttpException("At least one group must have write access on the folder.");
        }

        $mandatoryFolderFields = $vault->getMandatoryFolderFields() ?? [];
        if (is_null($createDto->getExternalId()) && in_array(FolderField::ExternalId, $mandatoryFolderFields)) {
            throw new BadRequestHttpException("External ID is mandatory for this vault.");
        }

        $newFolder = new Folder()->setName($createDto->getName())
                                 ->setExternalId($createDto->getExternalId())
                                 ->setVault($vault)
                                 ->setCreatedBy($loggedInUserIdentifier);

        $entityManager->persist($newFolder);

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

        if (!is_null($parent)) {
            $newFolder->setParent($parent);

            // Check if the parent is missing groups if explicit groups are provided.
            if ($explicitGroupsProvided) {
                $parentGroupIds = $parent->getGroupIds();
                $extraGroupIds = array_diff($groupIds, $parentGroupIds);

                if (!empty($extraGroupIds)) {
                    $this->addMissingGroupsToParentFolder(
                        $parent,
                        $groups,
                        $loggedInUserIdentifier,
                        $entityManager
                    );
                }

                $excludedGroupIds = array_diff($parentGroupIds, $groupIds);
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

        if ($explicitGroupsProvided) {
            // Check if the vault is missing groups if explicit groups are provided.
            $this->addMissingGroupsToVault($vault, $groups, $loggedInUserIdentifier, $entityManager);

            $excludedGroupIds = array_diff($vault->getGroupIds(), $groupIds);
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
