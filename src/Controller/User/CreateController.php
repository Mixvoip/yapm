<?php

namespace App\Controller\User;

use App\Controller\User\Dto\CreateDto;
use App\Entity\User;
use App\Normalizer\UserNormalizer;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CreateController extends AbstractController
{
    /**
     * @param  CreateDto  $createDto
     * @param  UserRepository  $userRepository
     * @param  EmailService  $emailService
     *
     * @return JsonResponse
     * @throws RandomException
     */
    #[Route('/users', name: 'api_users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        #[MapRequestPayload] CreateDto $createDto,
        UserRepository $userRepository,
        EmailService $emailService
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $user = new User()->setEmail($createDto->getEmail())
                          ->setUsername($createDto->getUsername())
                          ->setAdmin($createDto->isAdmin())
                          ->setCreatedBy($loggedInUser->getUserIdentifier())
                          ->setVerificationToken(bin2hex(random_bytes(16)));

        try {
            $userRepository->save($user, true);
        } catch (Exception) {
            throw new BadRequestHttpException("Unable to create user.");
        }

        try {
            $emailService->sendInvitationEmail($user);
        } catch (TransportExceptionInterface $e) {
            return $this->json(
                [
                    'message' => 'Failed to send email.',
                    'error' => $e->getMessage(),
                ],
                502
            );
        }

        return $this->json($user, 201, context: [UserNormalizer::WITH_GROUPS]);
    }
}
