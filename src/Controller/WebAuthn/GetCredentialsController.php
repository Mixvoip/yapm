<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Entity\User;
use App\Normalizer\WebAuthnCredentialNormalizer;
use App\Repository\WebAuthnCredentialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetCredentialsController extends AbstractController
{
    /**
     * List all registered WebAuthn credentials for the authenticated user.
     *
     * @param  WebAuthnCredentialRepository  $credentialRepository
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/credentials',
        name: 'api_webauthn_credentials',
        methods: ['GET']
    )]
    public function index(WebAuthnCredentialRepository $credentialRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $credentials = $credentialRepository->findByUser($user);

        return $this->json($credentials, context: [WebAuthnCredentialNormalizer::LIST_VIEW]);
    }
}
