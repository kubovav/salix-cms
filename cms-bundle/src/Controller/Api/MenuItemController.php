<?php

declare(strict_types=1);

namespace Salix\Cms\Controller\Api;

use Salix\Cms\Entity\MenuItem;
use Salix\Cms\Repository\ContentPageRepository;
use Salix\Cms\Repository\MenuItemRepository;
use Salix\Cms\Service\ApiSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class MenuItemController extends AbstractController
{
    use ApiValidationTrait;

    public function __construct(
        private readonly ApiSerializer $apiSerializer,
        private readonly ValidatorInterface $validator,
        private readonly MenuItemRepository $menuItemRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/menu_items', name: 'api_menu_items_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->menuItemRepository->findBy([], ['menuName' => 'ASC', 'position' => 'ASC']);

        return new JsonResponse(array_map($this->apiSerializer->menuItem(...), $items));
    }

    #[Route('/api/menu_items/{id}', name: 'api_menu_items_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $item = $this->menuItemRepository->find($id);
        if (null === $item) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->apiSerializer->menuItem($item));
    }

    #[Route('/api/menu_items', name: 'api_menu_items_create', methods: ['POST'])]
    public function create(Request $request, ContentPageRepository $pageRepository): JsonResponse
    {
        $item = new MenuItem();
        if (($failure = $this->apply($item, $request->toArray(), $pageRepository)) instanceof JsonResponse) {
            return $failure;
        }

        $violations = $this->validator->validate($item);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $this->em->persist($item);
        $this->em->flush();

        return new JsonResponse($this->apiSerializer->menuItem($item), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/menu_items/{id}', name: 'api_menu_items_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request, ContentPageRepository $pageRepository): JsonResponse
    {
        $item = $this->menuItemRepository->find($id);
        if (null === $item) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (($failure = $this->apply($item, $request->toArray(), $pageRepository)) instanceof JsonResponse) {
            return $failure;
        }

        $violations = $this->validator->validate($item);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $this->em->flush();

        return new JsonResponse($this->apiSerializer->menuItem($item));
    }

    #[Route('/api/menu_items/{id}', name: 'api_menu_items_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->menuItemRepository->find($id);
        if (null === $item) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->em->remove($item);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(MenuItem $item, array $payload, ContentPageRepository $pageRepository): ?JsonResponse
    {
        if (\array_key_exists('label', $payload)) {
            $item->setLabel(\is_string($payload['label']) ? $payload['label'] : '');
        }

        if (\array_key_exists('url', $payload)) {
            $item->setUrl(\is_string($payload['url']) ? $payload['url'] : null);
        }

        if (\array_key_exists('position', $payload)) {
            $item->setPosition(\is_int($payload['position']) ? $payload['position'] : -1);
        }

        if (\array_key_exists('menuName', $payload)) {
            $item->setMenuName(\is_string($payload['menuName']) ? $payload['menuName'] : '');
        }

        if (\array_key_exists('enabled', $payload)) {
            $item->setEnabled((bool) $payload['enabled']);
        }

        if (\array_key_exists('page', $payload)) {
            $ref = $payload['page'];
            if (null === $ref) {
                $item->setPage(null);
            } elseif (\is_int($ref) && null !== ($page = $pageRepository->find($ref))) {
                $item->setPage($page);
            } else {
                return $this->singleViolationResponse('page', 'Unknown page.');
            }
        }

        if (\array_key_exists('parent', $payload)) {
            $ref = $payload['parent'];
            if (null === $ref) {
                $item->setParent(null);
            } elseif (\is_int($ref) && null !== ($parent = $this->menuItemRepository->find($ref)) && $parent !== $item) {
                $item->setParent($parent);
            } else {
                return $this->singleViolationResponse('parent', 'Unknown parent item.');
            }
        }

        return null;
    }
}
