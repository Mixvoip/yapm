<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Controller\AbstractJsonPatchController;
use App\Controller\WebAuthn\Dto\PatchCredentialDto;
use App\Entity\User;
use App\Exception\InvalidRequestBodyException;
use App\Repository\WebAuthnCredentialRepository;
use DateMalformedStringException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class PatchCredentialController extends AbstractJsonPatchController
{
    /**
     * Update a WebAuthn credential.
     *
     * @param  string  $id
     * @param  PatchCredentialDto  $dto
     * @param  Request  $request
     * @param  WebAuthnCredentialRepository  $credentialRepository
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     * @throws DateMalformedStringException
     * @throws InvalidRequestBodyException
     */
    #[Route(
        '/webauthn/credentials/{id}',
        name: 'api_webauthn_credentials_patch',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['PATCH']
    )]
    public function index(
        string $id,
        #[MapRequestPayload] PatchCredentialDto $dto,
        Request $request,
        WebAuthnCredentialRepository $credentialRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $credential = $credentialRepository->find($id);

        if (is_null($credential) || $credential->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException("Credential with id: $id not found.");
        }

        $this->initializePatchData($request);
        $this->addDefaultPatchData($credential, $dto);

        if ($this->patch()) {
            $credential->setUpdatedBy($user->getUserIdentifier());
            $entityManager->flush();
        }

        return $this->json($credential);
    }
}
