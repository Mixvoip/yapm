<?php

/**
 * @author bsteffan
 * @since 2025-09-15
 */

namespace App\Controller\Vault;

use App\Controller\Dto\GroupPermissionDto;
use App\Controller\GroupValidationTrait;
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
use Symfony\Component\Routing\Attribute\Route;

class CreateController extends AbstractController
{
    use GroupValidationTrait;

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

        $groupIds = array_map(fn(GroupPermissionDto $g) => $g->groupId, $createDto->groups);
        $this->assertNoDuplicateGroupIds($groupIds);

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $groups = $groupRepository->findByIds($groupIds, ["PARTIAL g.{id, name, private}"]);
        $this->assertGroupsExist($groupIds, $groups);
        $this->assertNoPrivateGroups($groups);

        $groupPermissions = [];
        foreach ($createDto->groups as $groupDto) {
            $groupPermissions[$groupDto->groupId] = $groupDto->canWrite;
        }

        $vault = new Vault()->setName($createDto->name)
                            ->setMandatoryPasswordFields($createDto->mandatoryPasswordFields)
                            ->setMandatoryFolderFields($createDto->mandatoryFolderFields)
                            ->setAllowPasswordsAtRoot($createDto->allowPasswordsAtRoot)
                            ->setIconName($createDto->iconName)
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
