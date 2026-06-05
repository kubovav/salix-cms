<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentPage;
use App\Repository\ContentPageRepository;
use App\Repository\MenuItemRepository;
use App\Repository\SiteSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class FrontendController extends AbstractController
{
    #[Route('/', name: 'frontend_home')]
    public function home(
        ContentPageRepository $contentPageRepository,
        MenuItemRepository $menuItemRepository,
        SiteSettingRepository $siteSettingRepository,
    ): Response {
        $menuItems = $menuItemRepository->findEnabledRootItemsForMenu('main');

        $homeSlug = $siteSettingRepository->get('home_page_slug');
        if (null !== $homeSlug) {
            $page = $contentPageRepository->findPublishedBySlug($homeSlug);
            if ($page instanceof ContentPage) {
                return $this->render('frontend/page.html.twig', [
                    'page' => $page,
                    'menuItems' => $menuItems,
                ]);
            }
        }

        $pages = $contentPageRepository->findPublishedOrdered();

        return $this->render('frontend/home.html.twig', [
            'pages' => $pages,
            'menuItems' => $menuItems,
        ]);
    }

    #[Route('/{slug}', name: 'frontend_page', requirements: ['slug' => '[a-zA-Z0-9_-]+'])]
    public function page(
        string $slug,
        ContentPageRepository $contentPageRepository,
        MenuItemRepository $menuItemRepository,
    ): Response {
        $page = $contentPageRepository->findPublishedBySlug($slug);
        if (!$page instanceof ContentPage) {
            throw new NotFoundHttpException('Page not found.');
        }

        $menuItems = $menuItemRepository->findEnabledRootItemsForMenu('main');

        return $this->render('frontend/page.html.twig', [
            'page' => $page,
            'menuItems' => $menuItems,
        ]);
    }
}
