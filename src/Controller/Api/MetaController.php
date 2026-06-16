<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Config\BlockType;
use App\Config\MenuType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Provides enum choices (block types, menu names) for the admin UI.
 */
#[IsGranted('ROLE_ADMIN')]
final class MetaController extends AbstractController
{
    #[Route('/api/admin/meta', name: 'api_admin_meta', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'blockTypes' => array_map(
                static fn (BlockType $type): array => ['value' => $type->value, 'label' => $type->label()],
                BlockType::cases(),
            ),
            'menuNames' => array_map(
                static fn (MenuType $type): string => $type->value,
                MenuType::cases(),
            ),
        ]);
    }
}
