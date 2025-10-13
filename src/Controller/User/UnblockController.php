<?php

/**
 * @author bsteffan
 * @since 2025-05-26
 */

namespace App\Controller\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UnblockController extends AbstractController
{
    /**
     * Unblock user access.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     *
     * @return Response
     */
    #[Route(
        '/users/{id}/unblock',
        name: 'api_users_unblock',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['POST']
    )]
    #[isGranted('ROLE_ADMIN')]
    public function index(string $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findByIds(
            [$id],
            ["PARTIAL u.{id, username, email, active, updatedAt, updatedBy}"]
        )[$id] ?? null;

        if (is_null($user)) {
            throw $this->createNotFoundException("User with id: $id not found.");
        }

        $user->setActive(true)
             ->setUpdatedBy($loggedInUser->getUserIdentifier());

        $entityManager->persist($user);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
