<?php

/**
 * @author bsteffan
 * @since 2025-11-24
 */

namespace App\Controller\User;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\Group;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\Vault;
use App\Repository\GroupRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Repository\VaultRepository;
use App\Service\EmailService;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ResetController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Reset a user to their initial invited state.
     * Deletes private vault, all group memberships, and regenerates invitation token.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     * @param  EmailService  $emailService
     *
     * @return Response
     * @throws RandomException
     */
    #[Route(
        '/users/{id}/reset',
        name: 'api_users_reset',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function reset(
        string $id,
        EntityManagerInterface $entityManager,
        #[MapRequestPayload] EncryptedClientDataDto $encryptedPassword,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService,
        EmailService $emailService
    ): Response {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;

        // Validate admin's password
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

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findByIds(
            [$id],
            [
                "PARTIAL u.{id, username, verified, active, email, password, publicKey, encryptedPrivateKey, privateKeyNonce, keySalt, verificationToken, updatedBy}",
                "gu",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            groupUserAlias: "gu"
        )[$id] ?? null;

        if (is_null($user)) {
            throw $this->createNotFoundException("User with id: $id not found.");
        }

        // Prevent admin from resetting themselves
        if ($user->getId() === $loggedInUser->getId()) {
            throw $this->createAccessDeniedException("You cannot reset your own account.");
        }

        // Only verified users can be reset
        if (!$user->isVerified()) {
            return $this->json(
                [
                    'error' => "Bad Request",
                    'message' => "Cannot reset user: User is not verified.",
                ],
                400
            );
        }

        foreach ($user->getGroupUsers() as $groupUser) {
            $entityManager->remove($groupUser);
        }
        $user->getGroupUsers()->clear();

        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        /** @var Vault $privateVault */
        $privateVault = $vaultRepository->findOneBy(['user' => $id]);
        $entityManager->remove($privateVault);

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        /** @var Group $privateGroup */
        $privateGroup = $groupRepository->findOneBy(['name' => 'user-' . $id, 'private' => true]);
        $entityManager->remove($privateGroup);

        /** @var RefreshTokenRepository $refreshTokenRepository */
        $refreshTokenRepository = $entityManager->getRepository(RefreshToken::class);
        $refreshTokenRepository->invalidateAllForUser($user->getEmail());

        $user->setPassword(null)
             ->setVerified(false)
             ->setVerificationToken(bin2hex(random_bytes(16)))
             ->setPublicKey(null)
             ->setEncryptedPrivateKey(null)
             ->setPrivateKeyNonce(null)
             ->setKeySalt(null)
             ->setActive(true)
             ->setUpdatedBy($loggedInUser->getUserIdentifier());

        $entityManager->persist($user);
        $entityManager->flush();

        try {
            $emailService->sendInvitationEmail($user);
        } catch (TransportExceptionInterface $e) {
            return $this->json(
                [
                    'message' => "Failed to send email.",
                    'error' => $e->getMessage(),
                ],
                502
            );
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
