<?php

/**
 * @author bsteffan
 * @since 2025-10-03
 */

namespace App\Controller\Group;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\Group;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeleteController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Delete a group.
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
        "/groups/{id}/delete",
        name: "group_delete",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"]
    )]
    #[IsGranted('ROLE_ADMIN')]
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

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $group = $groupRepository->findByIds(
            [$id],
            ["PARTIAL g.{id, name, private, createdAt, updatedAt, deletedAt, deletedBy}"]
        )[$id] ?? null;

        if (is_null($group)) {
            throw $this->createNotFoundException("Group with id: $id not found.");
        }

        if ($group->isPrivate()) {
            throw new BadRequestHttpException("Private groups cannot be deleted.");
        }

        $group->markAsDeleted($loggedInUser->getUserIdentifier());
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
