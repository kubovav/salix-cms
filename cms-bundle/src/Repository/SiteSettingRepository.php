<?php

declare(strict_types=1);

namespace Salix\Cms\Repository;

use Salix\Cms\Entity\SiteSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSetting>
 */
class SiteSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSetting::class);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->findOneBy(['settingKey' => $key]);

        return $setting?->getSettingValue() ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        $setting = $this->findOneBy(['settingKey' => $key]);

        if (null === $value) {
            if (null !== $setting) {
                $this->getEntityManager()->remove($setting);
            }
        } elseif (null === $setting) {
            $this->getEntityManager()->persist(new SiteSetting($key, $value));
        } else {
            $setting->setSettingValue($value);
        }
    }
}
