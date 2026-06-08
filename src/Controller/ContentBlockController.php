<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\BlockType;
use App\Entity\ContentBlock;
use App\Form\Block\CtaBlockType;
use App\Form\Block\HeroBlockType;
use App\Form\Block\ImageBlockType;
use App\Form\Block\RichTextBlockType;
use App\Form\Block\TextImageBlockType;
use App\Repository\ContentBlockRepository;
use App\Repository\ContentPageRepository;
use App\Service\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/articles/{pageId}/blocks')]
#[IsGranted('ROLE_ADMIN')]
final class ContentBlockController extends AbstractController
{
    public function __construct(
        private readonly ContentPageRepository $pageRepository,
        private readonly ContentBlockRepository $blockRepository,
        private readonly EntityManagerInterface $em,
        private readonly ImageUploadService $uploader,
    ) {
    }

    #[Route('/new', name: 'admin_block_new', methods: ['GET', 'POST'])]
    public function new(int $pageId, Request $request): Response
    {
        $page = $this->pageRepository->find($pageId);
        if (null === $page) {
            throw $this->createNotFoundException('Article not found.');
        }

        $typeValue = $request->query->get('type');
        $blockType = $typeValue ? BlockType::tryFrom($typeValue) : null;

        if (null === $blockType) {
            // Show block type selector
            return $this->render('admin/articles/blocks/type_select.html.twig', [
                'page' => $page,
                'blockTypes' => BlockType::cases(),
            ]);
        }

        $isEdit = false;
        $form = $this->buildBlockForm($blockType, [], $request, $isEdit);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $this->extractData($blockType, $form, $request);

            $block = new ContentBlock();
            $block->setType($blockType->value);
            $block->setData($data);
            $block->setPosition($this->nextPosition($page->getId()));
            $page->addBlock($block);

            $this->em->persist($block);
            $this->em->flush();

            $this->addFlash('success', sprintf('%s block added.', $blockType->label()));

            return $this->redirectToRoute('admin_articles_edit', ['id' => $pageId]);
        }

        return $this->render('admin/articles/blocks/form.html.twig', [
            'page' => $page,
            'blockType' => $blockType,
            'form' => $form,
            'block' => null,
            'isEdit' => $isEdit,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_block_edit', methods: ['GET', 'POST'])]
    public function edit(int $pageId, int $id, Request $request): Response
    {
        $page = $this->pageRepository->find($pageId);
        $block = $this->blockRepository->find($id);

        if (null === $page || null === $block || $block->getPage()?->getId() !== $pageId) {
            throw $this->createNotFoundException('Block not found.');
        }

        $blockType = BlockType::from($block->getType());
        $isEdit = true;
        $form = $this->buildBlockForm($blockType, $block->getData(), $request, $isEdit);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $this->extractData($blockType, $form, $request, $block->getData());
            $block->setData($data);
            $this->em->flush();

            $this->addFlash('success', 'Block updated.');

            return $this->redirectToRoute('admin_articles_edit', ['id' => $pageId]);
        }

        return $this->render('admin/articles/blocks/form.html.twig', [
            'page' => $page,
            'blockType' => $blockType,
            'form' => $form,
            'block' => $block,
            'isEdit' => $isEdit,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_block_delete', methods: ['POST'])]
    public function delete(int $pageId, int $id, Request $request): RedirectResponse
    {
        $block = $this->blockRepository->find($id);

        if (null === $block || $block->getPage()?->getId() !== $pageId) {
            throw $this->createNotFoundException('Block not found.');
        }

        if (!$this->isCsrfTokenValid('delete_block_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_articles_edit', ['id' => $pageId]);
        }

        // Delete uploaded image file(s) if present
        $this->deleteBlockImages($block);

        $this->em->remove($block);
        $this->em->flush();

        $this->reorderBlocks($pageId);

        $this->addFlash('success', 'Block deleted.');

        return $this->redirectToRoute('admin_articles_edit', ['id' => $pageId]);
    }

    #[Route('/{id}/move', name: 'admin_block_move', methods: ['POST'])]
    public function move(int $pageId, int $id, Request $request): RedirectResponse
    {
        $block = $this->blockRepository->find($id);
        $page = $this->pageRepository->find($pageId);

        if (null === $block || null === $page || $block->getPage()?->getId() !== $pageId) {
            throw $this->createNotFoundException('Block not found.');
        }

        $direction = $request->request->getString('dir');
        $blocks = $this->blockRepository->findBy(['page' => $pageId], ['position' => 'ASC']);

        foreach ($blocks as $i => $b) {
            if ($b->getId() === $id) {
                $swapIndex = 'up' === $direction ? $i - 1 : $i + 1;
                if (isset($blocks[$swapIndex])) {
                    $currentPos = $b->getPosition();
                    $b->setPosition($blocks[$swapIndex]->getPosition());
                    $blocks[$swapIndex]->setPosition($currentPos);
                    $this->em->flush();
                }

                break;
            }
        }

        return $this->redirectToRoute('admin_articles_edit', ['id' => $pageId]);
    }

    private function buildBlockForm(BlockType $blockType, array $data, Request $request, bool $isEdit): FormInterface
    {
        $formClass = match($blockType) {
            BlockType::RICH_TEXT => RichTextBlockType::class,
            BlockType::IMAGE => ImageBlockType::class,
            BlockType::HERO => HeroBlockType::class,
            BlockType::TEXT_IMAGE => TextImageBlockType::class,
            BlockType::CTA => CtaBlockType::class,
        };

        $options = ['data_class' => null];
        if (in_array($blockType, [BlockType::IMAGE, BlockType::TEXT_IMAGE], true)) {
            $options['is_edit'] = $isEdit;
        }

        $form = $this->createForm($formClass, $data ?: null, $options);
        $form->handleRequest($request);

        return $form;
    }

    /**
     * Extract & persist uploaded files; return the final data array.
     */
    private function extractData(BlockType $blockType, FormInterface $form, Request $request, array $existingData = []): array
    {
        /** @var array<string, mixed> $data */
        $data = $form->getData() ?? [];

        $fileField = match($blockType) {
            BlockType::IMAGE, BlockType::HERO, BlockType::TEXT_IMAGE => 'file',
            default => null,
        };

        if (null !== $fileField) {
            $uploadedFile = $request->files->get($form->getName())[$fileField] ?? null;
            if (null !== $uploadedFile) {
                // Delete old image if replacing
                if (isset($existingData['filename'])) {
                    $this->uploader->delete($existingData['filename']);
                }

                $data['filename'] = $this->uploader->upload($uploadedFile);
            } elseif (isset($existingData['filename'])) {
                // Keep existing filename when no new file uploaded
                $data['filename'] = $existingData['filename'];
            }

            unset($data[$fileField]);
        }

        return $data;
    }

    private function nextPosition(int $pageId): int
    {
        $blocks = $this->blockRepository->findBy(['page' => $pageId], ['position' => 'DESC'], 1);

        return [] === $blocks ? 0 : $blocks[0]->getPosition() + 1;
    }

    private function reorderBlocks(int $pageId): void
    {
        $blocks = $this->blockRepository->findBy(['page' => $pageId], ['position' => 'ASC']);
        foreach ($blocks as $i => $block) {
            $block->setPosition($i);
        }

        $this->em->flush();
    }

    private function deleteBlockImages(ContentBlock $block): void
    {
        $data = $block->getData();
        if (isset($data['filename'])) {
            $this->uploader->delete($data['filename']);
        }
    }
}
