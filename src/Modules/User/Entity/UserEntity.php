<?php

namespace App\Modules\User\Entity;

use App\Modules\User\Repository\UserEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserEntityRepository::class)]
class UserEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['task:list', 'task:detail'])]
    private ?string $email = null;
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    #[Groups(['task:list', 'task:detail'])]
    private Collection $userRoles;
    
    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;
    

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserEntity $createdBy = null;


    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;
    
    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;
    
    public function __construct()
    {
        $this->userRoles = new ArrayCollection();
        $this->isActive = true;
        $this->createdAt = new \DateTimeImmutable();
    }
    
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getEmail(): ?string
    {
        return $this->email;
    }
    
    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }
    
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
    
    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = [];
        
        // Role های از دیتابیس
        foreach ($this->userRoles as $role) {
            $roles[] = $role->getName();
        }
        
        // اطمینان از وجود ROLE_USER
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }
        
        return array_unique($roles);
    }
    
    /**
     * Role Entity methods
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }
    
    public function addUserRole(Role $role): static
    {
        if (!$this->userRoles->contains($role)) {
            $this->userRoles[] = $role;
        }
        return $this;
    }
    
    public function removeUserRole(Role $role): static
    {
        $this->userRoles->removeElement($role);
        return $this;
    }
    
    public function setUserRoles(Collection $roles): static
    {
        $this->userRoles = $roles;
        return $this;
    }
    
    // برای backward compatibility
    public function setRoles(array $roles): static
    {
        // این method رو خالی نگه دار یا پیاده‌سازی کن
        return $this;
    }
    
    public function getPassword(): string
    {
        return $this->password;
    }
    
    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }
    
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }
    
    public function getCreatedBy(): ?UserEntity
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserEntity $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
    
    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
    
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
