<?php

namespace App\Controller\User;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BlockController extends AbstractController
{
    /**
     * Block user access.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     *
     * @return Response
     */
    #[Route(
        '/users/{id}/block',
        name: 'api_users_block',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function block(string $id, EntityManagerInterface $entityManager): Response
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

        // Check if the user is trying to block themselves
        if ($user->getId() === $loggedInUser->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Check if the user to be blocked is an admin and if they're the last admin
        if ($user->isAdmin() && $userRepository->getAdminCount() <= 1) {
            throw $this->createAccessDeniedException();
        }

        // Set user as inactive
        $user->setActive(false)
             ->setUpdatedBy($loggedInUser->getUserIdentifier());

        // Invalidate all refresh tokens for this user
        /** @var RefreshTokenRepository $refreshTokenRepository */
        $refreshTokenRepository = $entityManager->getRepository(RefreshToken::class);
        $refreshTokenRepository->invalidateAllForUser($user->getEmail());

        // Save changes to the user
        $entityManager->persist($user);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
