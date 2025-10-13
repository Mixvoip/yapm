<?php

namespace App\EventListener;

use App\Entity\Enums\AuditAction;
use App\Entity\User;
use App\Service\Audit\AuditService;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_failure')]
#[AsEventListener(event: 'gesdinet.refresh_token')]
readonly class JWTEventListener
{
    /**
     * @param  AuditService  $auditService
     */
    public function __construct(
        private AuditService $auditService
    ) {
    }

    /**
     * @param  JWTCreatedEvent  $event
     *
     * @return void
     */
    public function onLexikJWTAuthenticationOnJWTCreated(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();

        /** @var User $user */
        $user = $event->getUser();
        $payload['username'] = $user->getUsername();
        $payload['user_id'] = $user->getId();
        $event->setData($payload);
    }

    /**
     * @param  AuthenticationSuccessEvent  $event
     *
     * @return void
     */
    public function onLexikJwtAuthenticationOnAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        $this->auditService->log(AuditAction::SuccessfulLogin, $user);
    }

    /**
     * @param  AuthenticationFailureEvent  $event
     *
     * @return void
     */
    public function onLexikJwtAuthenticationOnAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $this->auditService->logFailedLoginAttempt(
            $request->getPayload()->get('email') ?? "no email",
            $response->getContent()
        );
    }

    /**
     * @param  RefreshEvent  $event
     *
     * @return void
     */
    public function onGesdinetRefreshToken(RefreshEvent $event): void
    {
        /** @var User $user */
        $user = $event->getToken()->getUser();
        $this->auditService->log(AuditAction::RefreshedToken, $user);
    }
}
