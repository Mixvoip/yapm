<?php

/**
 * @author bsteffan
 * @since 2025-06-27
 */

namespace App\EventListener;

use App\Service\Encryption\EncryptionService;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LoginRequestTransformer implements EventSubscriberInterface
{
    /**
     * @param  EncryptionService  $encryptionService
     */
    public function __construct(
        private EncryptionService $encryptionService
    ) {
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape(["\Symfony\Component\HttpKernel\KernelEvents::REQUEST" => "array"])]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10], // High priority to run early
        ];
    }

    /**
     * @param  RequestEvent  $event
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only process login_check requests
        if ($request->getPathInfo() !== '/login_check' || !$request->isMethod('POST')) {
            return;
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                return;
            }

            // Check if the request has plain credentials (reject these)
            if (isset($data['email']) || isset($data['password'])) {
                // Create an error response and stop processing
                $response = new JsonResponse([
                    'error' => "HTTP Error",
                    'message' => 'Plain credentials are not allowed. Use encrypted credentials only.',
                ], 400);

                $event->setResponse($response);
                return;
            }

            // Only process if we have encrypted credentials
            if (!isset($data['encryptedCredentials'])) {
                $response = new JsonResponse([
                    'error' => "HTTP Error",
                    'message' => 'Missing encrypted credentials. Plain credentials are not allowed.',
                ], 400);

                $event->setResponse($response);
                return;
            }

            // Decrypt the credentials
            $credentials = $this->decryptCredentials($data['encryptedCredentials']);

            // Create the new request content with decrypted credentials
            $newContent = json_encode([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]);

            // Replace the request content
            $this->replaceRequestContent($request, $newContent);
        } catch (Exception $e) {
            // Return an error response instead of letting the request continue
            $response = new JsonResponse([
                'error' => "HTTP Error",
                'message' => 'Invalid encrypted credentials: ' . $e->getMessage(),
            ], 400);

            $event->setResponse($response);
        }
    }

    /**
     * Decrypt the encrypted credentials from client
     */
    private function decryptCredentials(array $encryptedData): array
    {
        // Validate required fields for encryption
        $requiredFields = ['encryptedData', 'clientPublicKey', 'nonce'];
        foreach ($requiredFields as $field) {
            if (empty($encryptedData[$field])) {
                throw new RuntimeException("Missing or empty required field: $field");
            }
        }

        // Decrypt the credentials
        $decryptedJson = $this->encryptionService->decryptFromClient(
            $encryptedData['encryptedData'],
            $encryptedData['clientPublicKey'],
            $encryptedData['nonce']
        );

        if (empty($decryptedJson)) {
            throw new RuntimeException('Decryption resulted in empty data');
        }

        $credentials = json_decode($decryptedJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid encrypted credentials format: ' . json_last_error_msg());
        }

        if (!is_array($credentials)) {
            throw new RuntimeException('Decrypted credentials must be a JSON object');
        }

        // Validate that decrypted credentials contain required fields
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            throw new RuntimeException('Decrypted credentials missing required email or password fields');
        }

        return $credentials;
    }

    /**
     * Replace the request content by creating a new request with the decrypted content
     */
    private function replaceRequestContent(Request $request, string $newContent): void
    {
        // Create a new request with the decrypted content
        $newRequest = Request::create(
            $request->getUri(),
            $request->getMethod(),
            [],
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $newContent
        );

        // Copy headers
        $newRequest->headers->replace($request->headers->all());

        // Replace the current request's properties
        $request->initialize(
            $newRequest->query->all(),
            json_decode($newContent, true), // request parameters
            $newRequest->attributes->all(),
            $newRequest->cookies->all(),
            $newRequest->files->all(),
            $newRequest->server->all(),
            $newContent
        );
    }
}
