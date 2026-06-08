<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploadService
{
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private readonly string $uploadsDir)
    {
    }

    /**
     * Validates, moves and returns the stored filename.
     *
     * @throws \InvalidArgumentException on unsupported MIME type
     * @throws FileException             on move failure
     */
    public function upload(UploadedFile $file): string
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported image type "%s". Allowed: jpeg, png, gif, webp.', $file->getMimeType()));
        }

        $filename = bin2hex(random_bytes(16)).'.'.$file->guessExtension();

        $file->move($this->uploadsDir, $filename);

        return $filename;
    }

    public function getPublicPath(string $filename): string
    {
        return '/uploads/images/'.$filename;
    }

    public function delete(string $filename): void
    {
        $path = $this->uploadsDir.'/'.$filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
