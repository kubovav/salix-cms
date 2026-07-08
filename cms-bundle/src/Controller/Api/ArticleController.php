<?php

declare(strict_types=1);

namespace Salix\Cms\Controller\Api;

use Salix\Cms\Entity\ContentPage;
use Salix\Cms\Repository\ContentPageRepository;
use Salix\Cms\Service\ApiSerializer;
use Salix\Cms\Service\ContentPagePersister;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class ArticleController extends AbstractController
{
    use ApiValidationTrait;

    public function __construct(
        private readonly ApiSerializer $apiSerializer,
        private readonly ValidatorInterface $validator,
        private readonly ContentPageRepository $pageRepository,
    ) {
    }

    #[Route('/api/articles', name: 'api_articles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $articles = $this->pageRepository->findBy([], ['updatedAt' => 'DESC']);

        return new JsonResponse(array_map($this->apiSerializer->article(...), $articles));
    }

    #[Route('/api/articles/{id}', name: 'api_articles_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $page = $this->pageRepository->find($id);
        if (null === $page) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->apiSerializer->article($page));
    }

    #[Route('/api/articles', name: 'api_articles_create', methods: ['POST'])]
    public function create(Request $request, ContentPagePersister $persister): JsonResponse
    {
        $page = new ContentPage();
        $this->apply($page, $request->toArray());

        $violations = $this->validator->validate($page);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $persister->save($page);

        return new JsonResponse($this->apiSerializer->article($page), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/articles/{id}', name: 'api_articles_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request, ContentPagePersister $persister): JsonResponse
    {
        $page = $this->pageRepository->find($id);
        if (null === $page) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->apply($page, $request->toArray());

        $violations = $this->validator->validate($page);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $persister->save($page);

        return new JsonResponse($this->apiSerializer->article($page));
    }

    #[Route('/api/articles/{id}', name: 'api_articles_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $page = $this->pageRepository->find($id);
        if (null === $page) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $em->remove($page);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(ContentPage $page, array $payload): void
    {
        if (\array_key_exists('title', $payload)) {
            $page->setTitle(\is_string($payload['title']) ? $payload['title'] : '');
        }

        if (\array_key_exists('slug', $payload)) {
            $page->setSlug(\is_string($payload['slug']) ? $payload['slug'] : '');
        }

        if (\array_key_exists('metaDescription', $payload)) {
            $page->setMetaDescription(\is_string($payload['metaDescription']) ? $payload['metaDescription'] : null);
        }

        if (\array_key_exists('published', $payload)) {
            $page->setPublished((bool) $payload['published']);
        }
    }
}
