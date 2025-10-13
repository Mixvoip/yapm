<?php

namespace App\Controller\User;

use App\Controller\User\Dto\RegisterDto;
use App\Entity\Group;
use App\Entity\GroupsUser;
use App\Entity\GroupsVault;
use App\Entity\User;
use App\Entity\Vault;
use App\Repository\UserRepository;
use App\Service\Encryption\EncryptionService;
use App\Service\Utility\Base64UrlHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    /**
     * Complete user registration.
     *
     * @param  string  $token
     * @param  RegisterDto  $registerDto
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     * @throws RandomException
     */
    #[Route('/register/{token}', name: 'api_user_registration', methods: ['POST'])]
    public function index(
        string $token,
        #[MapRequestPayload] RegisterDto $registerDto,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService,
        EntityManagerInterface $entityManager
    ): Response {
        // Find the user by verification token
        $verificationToken = Base64UrlHelper::decode($token);

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        try {
            $user = $userRepository->findForVerification($verificationToken);
        } catch (NonUniqueResultException) {
            throw new BadRequestHttpException("Invalid token.");
        }

        if (is_null($user)) {
            throw new BadRequestHttpException("Invalid token.");
        }

        $encryptedPassword = $registerDto->getEncryptedPassword();
        $plainPassword = $encryptionService->decryptFromClient(
            $encryptedPassword->encryptedData,
            $encryptedPassword->clientPublicKey,
            $encryptedPassword->nonce
        );

        if (!preg_match("/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/", $plainPassword)) {
            throw new BadRequestHttpException(
                "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character."
            );
        }

        $userKeypair = $encryptionService->generateUserKeypair($plainPassword);

        // Complete registration and set the password
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword))
             ->setVerified(true)
             ->setVerificationToken(null)
             ->setUpdatedBy($user->getUserIdentifier())
             ->setPublicKey($userKeypair['publicKey'])
             ->setKeySalt($userKeypair['keySalt'])
             ->setEncryptedPrivateKey($userKeypair['encryptedPrivateKey'])
             ->setPrivateKeyNonce($userKeypair['privateKeyNonce']);

        $encryptionService->secureMemzero($userKeypair['privateKey']);
        $entityManager->persist($user);

        // Create a private group for the user.
        $groupKeys = $encryptionService->generateGroupKeypair();
        $group = new Group()->setName("user-" . $user->getId())
                            ->setPrivate(true)
                            ->setPublicKey($groupKeys['publicKey'])
                            ->setCreatedBy($user->getUserIdentifier());
        $entityManager->persist($group);

        $encryptedGroupKey = $encryptionService->encryptGroupPrivateKeyForUser(
            $groupKeys['privateKey'],
            $userKeypair['publicKey']
        );

        $groupUser = new GroupsUser()->setGroup($group)
                                     ->setUser($user)
                                     ->setEncryptedGroupPrivateKey($encryptedGroupKey['encryptedGroupPrivateKey'])
                                     ->setGroupPrivateKeyNonce($encryptedGroupKey['groupPrivateKeyNonce'])
                                     ->setEncryptionPublicKey($encryptedGroupKey['encryptionPublicKey'])
                                     ->setCreatedBy($user->getUserIdentifier());
        $entityManager->persist($groupUser);

        $encryptionService->secureMemzero($groupKeys['privateKey']);

        // Create a private vault for the user.
        $vault = new Vault()->setName("Private vault")
                            ->setUser($user)
                            ->setIconName("folder_shared")
                            ->setCreatedBy($user->getUserIdentifier());
        $entityManager->persist($vault);

        $groupVault = new GroupsVault()->setGroup($group)
                                       ->setVault($vault)
                                       ->setCanWrite(true)
                                       ->setCreatedBy($user->getUserIdentifier());
        $entityManager->persist($groupVault);

        $entityManager->flush();
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
