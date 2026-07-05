<?php

declare(strict_types=1);

namespace Salix\Cms\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    /**
     * Liveness probe for container orchestration. Deliberately avoids the
     * database so a DB hiccup does not get the app container restarted.
     * Priority keeps it ahead of the frontend catch-all /{slug} route.
     */
    #[Route('/healthz', name: 'healthz', methods: ['GET'], priority: 10)]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
