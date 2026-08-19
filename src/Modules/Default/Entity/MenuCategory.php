<?php

namespace App\Modules\Default\Entity;

use App\Modules\Default\Repository\MenuCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MenuCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MenuCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/', message: 'menu_category.code_pattern')]
    private string $code = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $labelKey = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $icon = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Menu>
     */
    #[ORM\OneToMany(mappedBy: 'menuCategory', targetEntity: Menu::class)]
    private Collection $menus;

    public function __construct()
    {
        $this->menus = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = mb_strtolower(trim($code));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getLabelKey(): ?string
    {
        return $this->labelKey;
    }

    public function setLabelKey(?string $labelKey): self
    {
        if ($labelKey !== null) {
            $labelKey = trim($labelKey);
        }

        $this->labelKey = $labelKey !== '' ? $labelKey : null;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        if ($icon !== null) {
            $icon = trim($icon);
        }

        $this->icon = $icon !== '' ? $icon : null;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Menu>
     */
    public function getMenus(): Collection
    {
        return $this->menus;
    }

    public function addMenu(Menu $menu): self
    {
        if (!$this->menus->contains($menu)) {
            $this->menus->add($menu);
            $menu->setMenuCategory($this);
        }

        return $this;
    }

    public function removeMenu(Menu $menu): self
    {
        if ($this->menus->removeElement($menu) && $menu->getMenuCategory() === $this) {
            $menu->setMenuCategory(null);
        }

        return $this;
    }
}
