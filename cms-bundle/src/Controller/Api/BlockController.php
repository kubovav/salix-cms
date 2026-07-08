<?php

declare(strict_types=1);

namespace Salix\Cms\Controller\Api;

use Salix\Cms\Entity\ContentBlock;
use Salix\Cms\Repository\ContentBlockRepository;
use Salix\Cms\Repository\ContentPageRepository;
use Salix\Cms\Service\ApiSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class BlockController extends AbstractController
{
    use ApiValidationTrait;

    public function __construct(
        private readonly ApiSerializer $apiSerializer,
        private readonly ValidatorInterface $validator,
        private readonly ContentBlockRepository $blockRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/blocks/{id}', name: 'api_blocks_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $block = $this->blockRepository->find($id);
        if (null === $block) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->apiSerializer->block($block));
    }

    #[Route('/api/blocks', name: 'api_blocks_create', methods: ['POST'])]
    public function create(Request $request, ContentPageRepository $pageRepository): JsonResponse
    {
        $block = new ContentBlock();
        if (($failure = $this->apply($block, $request->toArray(), $pageRepository)) instanceof JsonResponse) {
            return $failure;
        }

        $violations = $this->validator->validate($block);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $this->em->persist($block);
        $this->em->flush();

        return new JsonResponse($this->apiSerializer->block($block), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/blocks/{id}', name: 'api_blocks_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request, ContentPageRepository $pageRepository): JsonResponse
    {
        $block = $this->blockRepository->find($id);
        if (null === $block) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (($failure = $this->apply($block, $request->toArray(), $pageRepository)) instanceof JsonResponse) {
            return $failure;
        }

        $violations = $this->validator->validate($block);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $this->em->flush();

        return new JsonResponse($this->apiSerializer->block($block));
    }

    #[Route('/api/blocks/{id}', name: 'api_blocks_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $block = $this->blockRepository->find($id);
        if (null === $block) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->em->remove($block);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(ContentBlock $block, array $payload, ContentPageRepository $pageRepository): ?JsonResponse
    {
        if (\array_key_exists('name', $payload)) {
            $block->setName(\is_string($payload['name']) && '' !== $payload['name'] ? $payload['name'] : null);
        }

        if (\array_key_exists('type', $payload)) {
            $block->setType(\is_string($payload['type']) ? $payload['type'] : '');
        }

        if (\array_key_exists('position', $payload)) {
            $block->setPosition(\is_int($payload['position']) ? $payload['position'] : 0);
        }

        if (\array_key_exists('data', $payload)) {
            /** @var array<string, mixed> $data */
            $data = \is_array($payload['data']) ? $payload['data'] : [];
            $block->setData($data);
        }

        if (\array_key_exists('anchor', $payload)) {
            $block->setAnchor(\is_string($payload['anchor']) && '' !== $payload['anchor'] ? $payload['anchor'] : null);
        }

        if (\array_key_exists('page', $payload)) {
            $ref = $payload['page'];
            if (!\is_int($ref) || null === ($page = $pageRepository->find($ref))) {
                return $this->singleViolationResponse('page', 'Unknown page.');
            }

            $block->setPage($page);
        }

        return null;
    }
}
