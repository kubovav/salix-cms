<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ContentPageRepository;
use App\Validator\DerivableSlug;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContentPageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_CONTENT_PAGE_SLUG', fields: ['slug'])]
#[ApiResource(
    shortName: 'Article',
    operations: [
        new GetCollection(order: ['updatedAt' => 'DESC']),
        new Get(),
        new Post(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['article:read']],
    denormalizationContext: ['groups' => ['article:write']],
    security: "is_granted('ROLE_ADMIN')",
)]
#[UniqueEntity(fields: ['slug'], message: 'This slug is already in use.')]
#[DerivableSlug]
class ContentPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['article:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['article:read', 'article:write'])]
    #[Assert\Length(max: 180)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Use lowercase letters, numbers and dashes only.')]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'article:write'])]
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column]
    #[Groups(['article:read', 'article:write'])]
    private bool $published = false;

    #[ORM\Column]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, MenuItem>
     */
    #[ORM\OneToMany(targetEntity: MenuItem::class, mappedBy: 'page')]
    private Collection $menuItems;

    /**
     * @var Collection<int, ContentBlock>
     */
    #[ORM\OneToMany(targetEntity: ContentBlock::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['article:read'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->menuItems = new ArrayCollection();
        $this->blocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[Groups(['article:read'])]
    public function getBlockCount(): int
    {
        return $this->blocks->count();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getMenuItems(): Collection
    {
        return $this->menuItems;
    }

    public function addMenuItem(MenuItem $menuItem): static
    {
        if (!$this->menuItems->contains($menuItem)) {
            $this->menuItems->add($menuItem);
            $menuItem->setPage($this);
        }

        return $this;
    }

    public function removeMenuItem(MenuItem $menuItem): static
    {
        // set the owning side to null (unless already changed)
        if ($this->menuItems->removeElement($menuItem) && $menuItem->getPage() === $this) {
            $menuItem->setPage(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ContentBlock>
     */
    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function addBlock(ContentBlock $block): static
    {
        if (!$this->blocks->contains($block)) {
            $this->blocks->add($block);
            $block->setPage($this);
        }

        return $this;
    }

    public function removeBlock(ContentBlock $block): static
    {
        if ($this->blocks->removeElement($block) && $block->getPage() === $this) {
            $block->setPage(null);
        }

        return $this;
    }
}
