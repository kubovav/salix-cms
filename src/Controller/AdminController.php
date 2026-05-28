<?php

namespace App\Controller;

use App\Entity\ContentPage;
use App\Form\ContentPageType;
use App\Repository\ContentPageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

            return $this->redirectToRoute('admin_articles_index');
        }

        return $this->render('admin/articles/new.html.twig', [
            'form' => $form,
        ]);
    }
}
