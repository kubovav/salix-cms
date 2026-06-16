<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\ImageUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Accepts a multipart image upload and returns the stored filename and its
 * public path. Block resources reference the returned filename in their data.
 */
#[IsGranted('ROLE_ADMIN')]
final class UploadController extends AbstractController
{
    #[Route('/api/admin/uploads', name: 'api_admin_uploads', methods: ['POST'])]
    public function __invoke(Request $request, ImageUploadService $uploader): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file uploaded.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $filename = $uploader->upload($file);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            return new JsonResponse(['error' => $invalidArgumentException->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'filename' => $filename,
            'publicPath' => $uploader->getPublicPath($filename),
        ], JsonResponse::HTTP_CREATED);
    }
}
