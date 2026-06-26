<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\ContentBlockRepository;
use App\Repository\ContentPageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Persists a new block order for an article. Body: {"ids": [3, 1, 2]} listing
 * every block id of the article in the desired order.
 */
#[IsGranted('ROLE_ADMIN')]
final class BlockReorderController extends AbstractController
{
    #[Route('/api/admin/articles/{id}/reorder-blocks', name: 'api_admin_reorder_blocks', methods: ['POST'])]
    public function __invoke(
        int $id,
        Request $request,
        ContentPageRepository $pageRepository,
        ContentBlockRepository $blockRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $page = $pageRepository->find($id);
        if (null === $page) {
            return new JsonResponse(['error' => 'Article not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        /** @var array{ids?: mixed} $payload */
        $payload = $request->toArray();
        $ids = $payload['ids'] ?? null;
        if (!\is_array($ids)) {
            return new JsonResponse(['error' => 'Expected an "ids" array.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $pageBlocks = $blockRepository->findBy(['page' => $id]);
        $byId = [];
        foreach ($pageBlocks as $block) {
            $byId[$block->getId()] = $block;
        }

        if (\count($ids) !== \count($byId)) {
            return new JsonResponse(['error' => 'The ids must list every block exactly once.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $position = 0;
        foreach ($ids as $blockId) {
            if (!\is_int($blockId) || !isset($byId[$blockId])) {
                return new JsonResponse(['error' => 'Unknown block id in payload.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $byId[$blockId]->setPosition($position++);
        }

        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
