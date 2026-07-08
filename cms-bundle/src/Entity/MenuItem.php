<?php

namespace Salix\Cms\Entity;

use Salix\Cms\Config\MenuType;
use Salix\Cms\Repository\MenuItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Label is required.')]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $url = null;

    #[ORM\ManyToOne(targetEntity: ContentPage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ContentPage $page = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Position must be zero or a positive integer.')]
    private int $position = 0;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['main', 'footer'], message: 'Invalid menu.')]
    private string $menuName = MenuType::MAIN->value;

    #[ORM\Column]
    private bool $enabled = true;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateFooterHasNoParent(ExecutionContextInterface $context): void
    {
        if (MenuType::FOOTER->value === $this->menuName && $this->parent instanceof MenuItem) {
            $context->buildViolation('Footer menu items cannot be nested under a parent item.')
                ->atPath('parent')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateUrl(ExecutionContextInterface $context): void
    {
        if (null === $this->url) {
            return;
        }

        // Relative paths and in-page anchors are accepted as-is.
        if (str_starts_with($this->url, '/') || str_starts_with($this->url, '#')) {
            return;
        }

        // Anything else must normalize to a valid absolute http(s) URL.
        if (!preg_match('#^https?://#i', $this->url) || false === filter_var($this->url, FILTER_VALIDATE_URL)) {
            $context->buildViolation('The URL "{{ value }}" is not a valid URL.')
                ->setParameter('{{ value }}', $this->url)
                ->atPath('url')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $this->normalizeUrl($url);

        return $this;
    }

    /**
     * Normalizes the URL: trims it, leaves relative paths and anchors ("/about",
     * "#section") untouched, and prefixes "https://" to a scheme-less external
     * value so it can be validated and resolved as an absolute external link.
     */
    private function normalizeUrl(?string $url): ?string
    {
        if (null === $url) {
            return null;
        }

        $url = trim($url);

        if ('' === $url) {
            return null;
        }

        // Relative path or in-page anchor — keep as typed.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        // Only prefix when the value has no scheme at all (don't mangle an
        // explicit non-http scheme like "ftp://" — validation rejects those).
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            return 'https://'.$url;
        }

        return $url;
    }

    public function getPage(): ?ContentPage
    {
        return $this->page;
    }

    public function setPage(?ContentPage $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getMenuName(): string
    {
        return $this->menuName;
    }

    public function setMenuName(string|MenuType $menuName): static
    {
        $this->menuName = $menuName instanceof MenuType ? $menuName->value : $menuName;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}
