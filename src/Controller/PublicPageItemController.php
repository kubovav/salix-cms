<?php

namespace App\Controller;

use App\Repository\ContentPageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPageItemController
{
    #[Route('/api/public/pages/{slug}', name: 'api_public_pages_item', methods: ['GET'])]
    public function __invoke(string $slug, ContentPageRepository $contentPageRepository): JsonResponse
    {
        $page = $contentPageRepository->findPublishedBySlug($slug);
        if ($page === null) {
            throw new NotFoundHttpException('Page not found.');
        }

        return new JsonResponse([
            'slug' => $page->getSlug(),
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'updatedAt' => $page->getUpdatedAt()?->format(\DateTimeInterface::RFC3339),
        ]);
    }
}