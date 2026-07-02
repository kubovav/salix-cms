<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\SiteSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes site-wide branding settings (name and logo) to every frontend
 * template as Twig globals, so the public layout can render them without
 * each controller having to pass them explicitly.
 */
final class SiteSettingsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly SiteSettingRepository $settings)
    {
    }

    public function getGlobals(): array
    {
        return [
            'site_name' => $this->settings->get('site_name', 'Salix CMS'),
            'brand_logo' => $this->settings->get('brand_logo'),
        ];
    }
}
