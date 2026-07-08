<?php

declare(strict_types=1);

namespace Salix\Cms\Controller\Api;

use Salix\Cms\Entity\User;
use Salix\Cms\Repository\UserRepository;
use Salix\Cms\Service\ApiSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    use ApiValidationTrait;

    public function __construct(
        private readonly ApiSerializer $apiSerializer,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/users', name: 'api_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $users = $this->userRepository->findBy([], ['email' => 'ASC']);

        return new JsonResponse(array_map($this->apiSerializer->user(...), $users));
    }

    #[Route('/api/users/{id}', name: 'api_users_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->apiSerializer->user($user));
    }

    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = new User();
        $this->apply($user, $request->toArray());

        $violations = $this->validator->validate($user, groups: ['Default', 'user:create']);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse($this->apiSerializer->user($user), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/users/{id}', name: 'api_users_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->apply($user, $request->toArray());

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            return $this->violationResponse($violations);
        }

        $this->em->flush();

        return new JsonResponse($this->apiSerializer->user($user));
    }

    #[Route('/api/users/{id}', name: 'api_users_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return new JsonResponse(['error' => 'Not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->em->remove($user);
        $this->em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(User $user, array $payload): void
    {
        if (\array_key_exists('email', $payload)) {
            $user->setEmail(\is_string($payload['email']) ? $payload['email'] : '');
        }

        if (\array_key_exists('name', $payload)) {
            $user->setName(\is_string($payload['name']) ? $payload['name'] : '');
        }

        if (\array_key_exists('roles', $payload)) {
            $roles = \is_array($payload['roles']) ? $payload['roles'] : [];
            $user->setRoles(array_values(array_filter($roles, \is_string(...))));
        }

        // An absent or empty password means "keep the current one" on update; on
        // create the user:create NotBlank still fires because plainPassword stays null.
        if (isset($payload['plainPassword']) && \is_string($payload['plainPassword']) && '' !== $payload['plainPassword']) {
            $user->setPlainPassword($payload['plainPassword']);
        }
    }
}
