<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Controller\TimeBasedShare;

use App\Entity\User;
use App\Repository\TimeBasedShareRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetController extends AbstractController
{
    /**
     * Get metadata for a time-based share (no sensitive data).
     * Accessible by both the creator and the recipient.
     *
     * @param  string  $id
     * @param  TimeBasedShareRepository  $repository
     *
     * @return JsonResponse
     */
    #[Route(
        "/time-based-shares/{id}",
        name: "api_time_based_shares_get",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(
        string $id,
        TimeBasedShareRepository $repository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $share = $repository->findActiveById($id);

        $isRecipient = !is_null($share) && $share->getSharedWith()->getId() === $loggedInUser->getId();
        $isCreator = !is_null($share) && $share->getSharedBy()->getId() === $loggedInUser->getId();

        if (!$isRecipient && !$isCreator) {
            throw $this->createNotFoundException("Time-based share not found.");
        }

        return $this->json($share);
    }
}
