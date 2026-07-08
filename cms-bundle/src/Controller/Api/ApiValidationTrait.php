<?php

declare(strict_types=1);

namespace Salix\Cms\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

trait ApiValidationTrait
{
    private function violationResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $items = [];
        foreach ($violations as $violation) {
            $items[] = [
                'propertyPath' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return new JsonResponse(['violations' => $items], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function singleViolationResponse(string $propertyPath, string $message): JsonResponse
    {
        return new JsonResponse(
            ['violations' => [['propertyPath' => $propertyPath, 'message' => $message]]],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
