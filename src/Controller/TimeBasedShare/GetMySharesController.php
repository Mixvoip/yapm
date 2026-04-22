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

class GetMySharesController extends AbstractController
{
    /**
     * Get all active time-based shares involving the current user (as creator or recipient).
     *
     * @param  TimeBasedShareRepository  $repository
     *
     * @return JsonResponse
     */
    #[Route(
        "/time-based-shares/my-shares",
        name: "api_time_based_shares_my_shares",
        methods: ["GET"]
    )]
    public function index(
        TimeBasedShareRepository $repository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $shares = $repository->findActiveForUser($loggedInUser->getId());

        return $this->json($shares);
    }
}
