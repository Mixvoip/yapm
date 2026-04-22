<?php

/**
 * @author bsteffan
 * @since 2026-02-17
 */

namespace App\Controller\TimeBasedShare;

use App\Entity\TimeBasedShare;
use App\Entity\User;
use App\Repository\TimeBasedShareRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RevokeController extends AbstractController
{
    /**
     * Revoke an active time-based share by setting its expiry to now.
     * Only the creator of the share can revoke it.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     *
     * @return Response
     */
    #[Route(
        "/time-based-shares/{id}/revoke",
        name: "api_time_based_shares_revoke",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"]
    )]
    public function index(
        string $id,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        /** @var TimeBasedShareRepository $repository */
        $repository = $entityManager->getRepository(TimeBasedShare::class);

        $share = $repository->findActiveById($id);

        if (is_null($share) || $share->getSharedBy()->getId() !== $loggedInUser->getId()) {
            throw $this->createNotFoundException("Time-based share not found.");
        }

        $share->setExpiresAt(new DateTimeImmutable());
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
