<?php
namespace App\Modules\User\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity()]
#[ORM\HasLifecycleCallbacks]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['task:list', 'task:detail'])]
    
    private string $name;
    
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Role $parent = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'role_permission')]
    private Collection $permissions;
    
    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->permissions = new ArrayCollection();
    }
    
    // Getters and setters...
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getName(): ?string
    {
        return $this->name;
    }
    
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    public function getParent(): ?Role
    {
        return $this->parent;
    }
    
    public function setParent(?Role $parent): self
    {
        if ($parent === $this) {
            throw new \InvalidArgumentException('A role cannot be its own parent.');
        }

        $this->parent = $parent;
        return $this;
    }
    
    /**
     * @return Collection<int, Role>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Role $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Role $child): self
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }
    
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }
    
    public function addPermission(Permission $permission): self
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions[] = $permission;
        }
        return $this;
    }
    
    public function removePermission(Permission $permission): self
    {
        $this->permissions->removeElement($permission);
        return $this;
    }
}
