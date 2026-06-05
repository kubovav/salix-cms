<?php

namespace App\Controller;

use App\Entity\ContentPage;
use App\Repository\ContentPageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPageCollectionController
{
    #[Route('/api/public/pages', name: 'api_public_pages_collection', methods: ['GET'])]
    public function __invoke(ContentPageRepository $contentPageRepository): JsonResponse
    {
        $pages = $contentPageRepository->findPublishedOrdered();

        $data = array_map(static fn (ContentPage $page): array => [
            'slug' => $page->getSlug(),
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'updatedAt' => $page->getUpdatedAt()?->format(\DateTimeInterface::RFC3339),
        ], $pages);

        return new JsonResponse($data);
    }
}
