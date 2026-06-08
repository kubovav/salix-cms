<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentPage;
use App\Form\ContentPageType;
use App\Repository\ContentPageRepository;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/articles', name: 'admin_articles_index')]
    public function articlesIndex(ContentPageRepository $repository): Response
    {
        return $this->render('admin/articles/index.html.twig', [
            'articles' => $repository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }

    #[Route('/articles/new', name: 'admin_articles_new')]
    public function articlesNew(Request $request, EntityManagerInterface $em): Response
    {
        $page = new ContentPage();
        $form = $this->createForm(ContentPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($page);
            $em->flush();

            $this->addFlash('success', 'Article created successfully.');

            return $this->redirectToRoute('admin_articles_edit', ['id' => $page->getId()]);
        }

        return $this->render('admin/articles/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/articles/{id}/edit', name: 'admin_articles_edit')]
    public function articlesEdit(int $id, Request $request, EntityManagerInterface $em, ContentPageRepository $repository): Response
    {
        $page = $repository->find($id);
        if (null === $page) {
            throw $this->createNotFoundException('Article not found.');
        }

        $form = $this->createForm(ContentPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Article updated.');

            return $this->redirectToRoute('admin_articles_edit', ['id' => $id]);
        }

        return $this->render('admin/articles/edit.html.twig', [
            'page' => $page,
            'form' => $form,
        ]);
    }

    #[Route('/articles/{id}/delete', name: 'admin_articles_delete', methods: ['POST'])]
    public function articlesDelete(int $id, Request $request, EntityManagerInterface $em, ContentPageRepository $repository): RedirectResponse
    {
        $page = $repository->find($id);
        if (null === $page) {
            throw $this->createNotFoundException('Article not found.');
        }

        if (!$this->isCsrfTokenValid('delete_article_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_articles_index');
        }

        $em->remove($page);
        $em->flush();

        $this->addFlash('success', 'Article deleted.');

        return $this->redirectToRoute('admin_articles_index');
    }

    #[Route('/settings', name: 'admin_settings')]
    public function settings(
        Request $request,
        EntityManagerInterface $em,
        ContentPageRepository $contentPageRepository,
        SiteSettingRepository $siteSettingRepository,
    ): Response {
        $pages = $contentPageRepository->findPublishedOrdered();
        $choices = [];
        foreach ($pages as $page) {
            $choices[$page->getTitle().' (/'.$page->getSlug().')'] = $page->getSlug();
        }

        $form = $this->createFormBuilder(['home_page_slug' => $siteSettingRepository->get('home_page_slug')])
            ->add('home_page_slug', ChoiceType::class, [
                'label' => 'Home Page',
                'choices' => $choices,
                'placeholder' => '— none (show page listing) —',
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{home_page_slug: string|null} $data */
            $data = $form->getData();
            $siteSettingRepository->set('home_page_slug', '' !== $data['home_page_slug'] ? $data['home_page_slug'] : null);
            $em->flush();

            $this->addFlash('success', 'Settings saved.');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/index.html.twig', [
            'form' => $form,
        ]);
    }
}
