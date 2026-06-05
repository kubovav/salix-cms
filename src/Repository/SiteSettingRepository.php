<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteSetting;
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

        if ($setting === null) {
            $setting = new SiteSetting($key, $value);
            $this->getEntityManager()->persist($setting);
        } else {
            $setting->setSettingValue($value);
        }

        $this->getEntityManager()->flush();
    }
}
