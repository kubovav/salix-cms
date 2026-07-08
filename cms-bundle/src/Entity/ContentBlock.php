<?php

declare(strict_types=1);

namespace Salix\Cms\Entity;

use Salix\Cms\Config\BlockType;
use Salix\Cms\Repository\ContentBlockRepository;
use Salix\Cms\Validator\ValidBlockData;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContentBlockRepository::class)]
#[ORM\Table(name: 'content_block')]
#[ValidBlockData]
class ContentBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [BlockType::class, 'values'], message: 'Unknown block type.')]
    private string $type;

    #[ORM\Column]
    private int $position = 0;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $renderedHtml = null;

    #[ORM\ManyToOne(targetEntity: ContentPage::class, inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?ContentPage $page = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[A-Za-z][A-Za-z0-9_-]*$/',
        message: 'Anchor must start with a letter and contain only letters, numbers, hyphens or underscores (no "#").'
    )]
    private ?string $anchor = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getBlockType(): BlockType
    {
        return BlockType::from($this->type);
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

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getRenderedHtml(): ?string
    {
        return $this->renderedHtml;
    }

    public function setRenderedHtml(?string $renderedHtml): static
    {
        $this->renderedHtml = $renderedHtml;

        return $this;
    }

    /**
     * Public URL of the block image, when the block stores one.
     */
    public function getImageUrl(): ?string
    {
        $filename = $this->data['filename'] ?? null;

        return \is_string($filename) && '' !== $filename ? '/uploads/images/'.$filename : null;
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

    public function getAnchor(): ?string
    {
        return $this->anchor;
    }

    public function setAnchor(?string $anchor): static
    {
        $this->anchor = $anchor;

        return $this;
    }
}
