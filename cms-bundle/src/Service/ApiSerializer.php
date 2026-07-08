<?php

declare(strict_types=1);

namespace Salix\Cms\Service;

use Salix\Cms\Entity\ContentBlock;
use Salix\Cms\Entity\ContentPage;
use Salix\Cms\Entity\MenuItem;
use Salix\Cms\Entity\User;

final class ApiSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function article(ContentPage $page): array
    {
        return [
            'id' => $page->getId(),
            'slug' => $page->getSlug(),
            'title' => $page->getTitle(),
            'metaDescription' => $page->getMetaDescription(),
            'published' => $page->isPublished(),
            'updatedAt' => $page->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'blockCount' => $page->getBlockCount(),
            'blocks' => array_map($this->block(...), $page->getBlocks()->getValues()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function block(ContentBlock $block): array
    {
        return [
            'id' => $block->getId(),
            'name' => $block->getName(),
            'type' => $block->getType(),
            'position' => $block->getPosition(),
            'data' => $block->getData(),
            'anchor' => $block->getAnchor(),
            'renderedHtml' => $block->getRenderedHtml(),
            'imageUrl' => $block->getImageUrl(),
            'page' => $block->getPage()?->getId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function menuItem(MenuItem $item): array
    {
        return [
            'id' => $item->getId(),
            'label' => $item->getLabel(),
            'url' => $item->getUrl(),
            'position' => $item->getPosition(),
            'menuName' => $item->getMenuName(),
            'enabled' => $item->isEnabled(),
            'page' => $item->getPage()?->getId(),
            'parent' => $item->getParent()?->getId(),
            'children' => array_map(static fn (MenuItem $child): ?int => $child->getId(), $item->getChildren()->getValues()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function user(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'name' => $user->getName(),
            'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
