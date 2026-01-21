<?php

/**
 * @author bsteffan
 * @since 2025-09-15
 */

namespace App\Controller\Vault;

use App\Controller\GroupValidationTrait;
use App\Controller\NulledValueGetterTrait;
use App\Controller\PermissionProcessingTrait;
use App\Controller\Vault\Dto\CreateDto;
use App\Entity\Group;
use App\Entity\GroupsVault;
use App\Entity\User;
use App\Entity\Vault;
use App\Normalizer\VaultNormalizer;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CreateController extends AbstractController
{
    use GroupValidationTrait;
    use NulledValueGetterTrait;
    use PermissionProcessingTrait;

    /**
     * Create a new vault.
     *
     * @param  CreateDto  $createDto
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     */
    #[Route("/vaults", name: "api_vaults_create", methods: ["POST"])]
    public function index(
        #[MapRequestPayload] CreateDto $createDto,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        // Vault requires at least 1 group OR at least 2 user permissions
        $groupCount = count($createDto->groups);
        $userCount = count($createDto->userPermissions);
        if ($groupCount === 0 && $userCount < 2) {
            throw new BadRequestHttpException("Vault requires at least one group or at least two user permissions.");
        }

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);

        // Process group permissions
        $groupResult = $this->processGroupPermissions($createDto->groups, $groupRepository);
        $groups = $groupResult['groups'];
        $groupPermissions = $groupResult['groupPermissions'];

        // Process user permissions (private groups)
        $userResult = $this->processUserPermissions($createDto->userPermissions, $groupRepository);
        $privateGroups = $userResult['privateGroups'];
        $userPermissions = $userResult['userPermissions'];
        $userIdToGroupId = $userResult['userIdToGroupId'];

        // Guard: if no groups are provided, ensure at least one user permission is for someone other than the creator
        if ($groupCount === 0 && $userCount > 0) {
            $loggedInUserId = $loggedInUser->getId();
            $otherUserIds = array_filter(
                array_keys($userIdToGroupId),
                fn($userId) => $userId !== $loggedInUserId
            );
            if (empty($otherUserIds)) {
                throw new BadRequestHttpException(
                    "When sharing with users only, at least one user must be someone other than yourself."
                );
            }
        }

        // Guard: at least one write-capable group or user must be present
        $hasWrite = array_any($groupPermissions, fn($canWrite) => $canWrite === true)
                    || array_any($userPermissions, fn($canWrite) => $canWrite === true);
        if (!$hasWrite) {
            throw new BadRequestHttpException("At least one group or user must have write access on the vault.");
        }

        $vault = new Vault()->setName($createDto->name)
                            ->setMandatoryPasswordFields($createDto->mandatoryPasswordFields)
                            ->setMandatoryFolderFields($createDto->mandatoryFolderFields)
                            ->setAllowPasswordsAtRoot($createDto->allowPasswordsAtRoot)
                            ->setIconName($createDto->iconName)
                            ->setDescription($this->getTrimmedOrNull($createDto->description))
                            ->setCreatedBy($loggedInUser->getUserIdentifier());

        $entityManager->persist($vault);

        foreach ($groups as $group) {
            $groupPermission = $groupPermissions[$group->getId()];
            $gv = new GroupsVault()->setVault($vault)
                                   ->setGroup($group)
                                   ->setCanWrite($groupPermission)
                                   ->setPartial(false)
                                   ->setCreatedBy($loggedInUser->getUserIdentifier());
            $entityManager->persist($gv);
            $vault->getGroupVaults()->add($gv);
        }

        // Create GroupsVault entries for user permissions (private groups)
        foreach ($privateGroups as $group) {
            $permission = $userPermissions[$group->getId()] ?? false;
            $gv = new GroupsVault()->setVault($vault)
                                   ->setGroup($group)
                                   ->setCanWrite($permission)
                                   ->setPartial(false)
                                   ->setCreatedBy($loggedInUser->getUserIdentifier());
            $entityManager->persist($gv);
            $vault->getGroupVaults()->add($gv);
        }

        $entityManager->flush();

        return $this->json(
            $vault,
            201,
            context: [
                VaultNormalizer::WITH_GROUPS,
                VaultNormalizer::WITH_MANDATORY_FIELDS,
            ]
        );
    }
}
