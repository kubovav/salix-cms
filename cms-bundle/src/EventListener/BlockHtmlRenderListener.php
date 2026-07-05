<?php

declare(strict_types=1);

namespace Salix\Cms\EventListener;

use Salix\Cms\Config\BlockType;
use Salix\Cms\Entity\ContentBlock;
use Salix\Cms\Service\RichTextRenderer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Renders a rich-text block's Quill Delta to sanitized HTML and caches it on the block.
 *
 * The Delta (in `data.delta`) is the canonical source; this derives the read-only
 * ContentBlock::$renderedHtml on every write, so the frontend never parses Delta at request time.
 */
#[AsEntityListener(event: Events::prePersist, entity: ContentBlock::class)]
#[AsEntityListener(event: Events::preUpdate, entity: ContentBlock::class)]
final readonly class BlockHtmlRenderListener
{
    public function __construct(
        private RichTextRenderer $renderer,
        #[Autowire(service: 'html_sanitizer.sanitizer.block_html')]
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    public function prePersist(ContentBlock $block): void
    {
        $this->renderHtml($block);
    }

    public function preUpdate(ContentBlock $block, PreUpdateEventArgs $args): void
    {
        $this->renderHtml($block);

        $em = $args->getObjectManager();
        $em->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $em->getClassMetadata(ContentBlock::class),
            $block,
        );
    }

    private function renderHtml(ContentBlock $block): void
    {
        $type = BlockType::tryFrom($block->getType());
        if (!\in_array($type, [BlockType::RICH_TEXT, BlockType::TEXT_IMAGE], true)) {
            $block->setRenderedHtml(null);

            return;
        }

        $delta = $block->getData()['delta'] ?? null;
        if (!\is_array($delta)) {
            $block->setRenderedHtml(null);

            return;
        }

        $html = $this->renderer->render($delta);
        $block->setRenderedHtml('' === $html ? null : $this->sanitizer->sanitize($html));
    }
}
