<?php

declare(strict_types=1);

namespace Salix\Cms\Controller;

use Salix\Cms\Config\MenuType;
use Salix\Cms\Entity\ContentPage;
use Salix\Cms\Repository\ContentPageRepository;
use Salix\Cms\Repository\MenuItemRepository;
use Salix\Cms\Repository\SiteSettingRepository;
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
        $menuItems = $menuItemRepository->findEnabledRootItemsForMenu(MenuType::MAIN);
        $footerMenuItems = $menuItemRepository->findEnabledRootItemsForMenu(MenuType::FOOTER);

        $homeSlug = $siteSettingRepository->get('home_page_slug');
        if (null !== $homeSlug) {
            $page = $contentPageRepository->findPublishedBySlug($homeSlug);
            if ($page instanceof ContentPage) {
                return $this->render('@SalixCms/frontend/page.html.twig', [
                    'page' => $page,
                    'isHome' => true,
                    'menuItems' => $menuItems,
                    'footerMenuItems' => $footerMenuItems,
                ]);
            }
        }

        $pages = $contentPageRepository->findPublishedOrdered();

        return $this->render('@SalixCms/frontend/home.html.twig', [
            'pages' => $pages,
            'menuItems' => $menuItems,
            'footerMenuItems' => $footerMenuItems,
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

        $menuItems = $menuItemRepository->findEnabledRootItemsForMenu(MenuType::MAIN);
        $footerMenuItems = $menuItemRepository->findEnabledRootItemsForMenu(MenuType::FOOTER);

        return $this->render('@SalixCms/frontend/page.html.twig', [
            'page' => $page,
            'menuItems' => $menuItems,
            'footerMenuItems' => $footerMenuItems,
        ]);
    }
}
