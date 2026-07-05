<?php

declare(strict_types=1);

namespace Salix\Cms\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Replaces the default logout redirect with an empty JSON response for the
 * admin SPA.
 */
#[AsEventListener(event: LogoutEvent::class)]
final class JsonLogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $event->setResponse(new JsonResponse(null, Response::HTTP_NO_CONTENT));
    }
}
