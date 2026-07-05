<?php

namespace Salix\Cms\Entity;

use Salix\Cms\Repository\SiteSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteSettingRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SITE_SETTING_KEY', fields: ['settingKey'])]
class SiteSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $settingKey;

    #[ORM\Column(length: 500)]
    private string $settingValue;

    public function __construct(string $settingKey, string $settingValue)
    {
        $this->settingKey = $settingKey;
        $this->settingValue = $settingValue;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function getSettingValue(): string
    {
        return $this->settingValue;
    }

    public function setSettingValue(string $value): static
    {
        $this->settingValue = $value;

        return $this;
    }
}
