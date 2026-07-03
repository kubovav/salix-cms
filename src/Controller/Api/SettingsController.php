<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ContentPage;
use App\Repository\ContentPageRepository;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Exposes the site settings managed by the admin (currently the home page),
 * along with the list of published pages available for selection.
 */
#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    #[Route('/api/admin/settings', name: 'api_admin_settings_get', methods: ['GET'])]
    public function get(ContentPageRepository $pageRepository, SiteSettingRepository $settingsRepository): JsonResponse
    {
        return new JsonResponse($this->buildPayload($pageRepository, $settingsRepository));
    }

    #[Route('/api/admin/settings', name: 'api_admin_settings_update', methods: ['PATCH'])]
    public function update(
        Request $request,
        ContentPageRepository $pageRepository,
        SiteSettingRepository $settingsRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array{home_page_slug?: mixed, site_name?: mixed, brand_logo?: mixed, meta_description?: mixed} $payload */
        $payload = $request->toArray();

        if (\array_key_exists('home_page_slug', $payload)) {
            $slug = $payload['home_page_slug'];
            $slug = \is_string($slug) && '' !== $slug ? $slug : null;

            if (null === $slug && [] !== $pageRepository->findPublishedOrdered()) {
                return new JsonResponse(['error' => 'A home page must be selected.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (null !== $slug && !$pageRepository->findPublishedBySlug($slug) instanceof ContentPage) {
                return new JsonResponse(['error' => 'Unknown published page slug.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $settingsRepository->set('home_page_slug', $slug);
        }

        if (\array_key_exists('site_name', $payload)) {
            $name = $payload['site_name'];
            $name = \is_string($name) ? trim($name) : '';
            $settingsRepository->set('site_name', '' !== $name ? $name : null);
        }

        if (\array_key_exists('brand_logo', $payload)) {
            $logo = $payload['brand_logo'];
            $logo = \is_string($logo) ? trim($logo) : '';
            $settingsRepository->set('brand_logo', '' !== $logo ? $logo : null);
        }

        if (\array_key_exists('meta_description', $payload)) {
            $description = $payload['meta_description'];
            $description = \is_string($description) ? trim($description) : '';

            if (mb_strlen($description) > 255) {
                return new JsonResponse(['error' => 'The meta description must be 255 characters or fewer.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $settingsRepository->set('meta_description', '' !== $description ? $description : null);
        }

        $em->flush();

        return new JsonResponse($this->buildPayload($pageRepository, $settingsRepository));
    }

    /**
     * @return array{home_page_slug: string|null, site_name: string|null, brand_logo: string|null, meta_description: string|null, available_pages: list<array{slug: string, title: string}>}
     */
    private function buildPayload(ContentPageRepository $pageRepository, SiteSettingRepository $settingsRepository): array
    {
        $available = array_map(
            static fn (ContentPage $page): array => ['slug' => $page->getSlug(), 'title' => $page->getTitle()],
            $pageRepository->findPublishedOrdered(),
        );

        return [
            'home_page_slug' => $settingsRepository->get('home_page_slug'),
            'site_name' => $settingsRepository->get('site_name', 'Salix CMS'),
            'brand_logo' => $settingsRepository->get('brand_logo'),
            'meta_description' => $settingsRepository->get('meta_description'),
            'available_pages' => $available,
        ];
    }
}
