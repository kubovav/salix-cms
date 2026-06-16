<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ContentBlock;
use App\Service\ImageUploadService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Deletes the uploaded image associated with a ContentBlock when it is removed
 * (directly or via cascade when its Article is deleted).
 */
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: ContentBlock::class)]
final readonly class ContentBlockImageCleanupListener
{
    public function __construct(private ImageUploadService $uploader)
    {
    }

    public function postRemove(ContentBlock $block): void
    {
        $filename = $block->getData()['filename'] ?? null;
        if (\is_string($filename) && '' !== $filename) {
            $this->uploader->delete($filename);
        }
    }
}
