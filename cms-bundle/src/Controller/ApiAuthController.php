<?php

declare(strict_types=1);

namespace Salix\Cms\Controller;

use Salix\Cms\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAuthController extends AbstractController
{
    /**
     * Returns the currently authenticated admin user, or 401 when anonymous.
     */
    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}
