<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Form\MenuItemType;
use App\Repository\MenuItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/menu')]
#[IsGranted('ROLE_ADMIN')]
final class MenuItemController extends AbstractController
{
    #[Route('', name: 'admin_menu_index')]
    public function index(MenuItemRepository $repository): Response
    {
        return $this->render('admin/menu/index.html.twig', [
            'items' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_menu_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new MenuItem();
        $form = $this->createForm(MenuItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();

            $this->addFlash('success', 'Menu item created successfully.');

            return $this->redirectToRoute('admin_menu_index');
        }

        return $this->render('admin/menu/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_menu_edit', requirements: ['id' => '\d+'])]
    public function edit(MenuItem $item, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(MenuItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Menu item updated successfully.');

            return $this->redirectToRoute('admin_menu_index');
        }

        return $this->render('admin/menu/edit.html.twig', [
            'form' => $form,
            'item' => $item,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_menu_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(MenuItem $item, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if ($this->isCsrfTokenValid('delete_menu_item_'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();

            $this->addFlash('success', 'Menu item deleted.');
        }

        return $this->redirectToRoute('admin_menu_index');
    }
}
