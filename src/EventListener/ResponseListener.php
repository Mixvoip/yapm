<?php

/**
 * @author bsteffan
 * @since 2025-07-04
 */

namespace App\EventListener;

use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class ResponseListener implements EventSubscriberInterface
{
    /**
     * @param  string  $allowOrigin
     */
    public function __construct(
        private string $allowOrigin
    ) {
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([KernelEvents::RESPONSE => "string[]"])]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    /**
     * @param  ResponseEvent  $event
     *
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->getPathInfo() === '/login_check') {
            $response->headers->set('Access-Control-Allow-Origin', $this->allowOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }
}

