<?php
namespace App\Modules\Default\Entity;

use App\Modules\Default\Repository\AppSettingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use DateTimeInterface;

/**
 * The AppSetting entity using PHP 8 attributes for Doctrine mapping.
 */
#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AppSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;
    
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $value = null;
    
    #[ORM\Column(type: 'datetime')]
    private ?DateTimeInterface $createdAt = null;
    
    #[ORM\Column(type: 'datetime')]
    private ?DateTimeInterface $updatedAt = null;
    
    /**
     * Getters and setters
     */
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
    
    public function getValue(): ?string
    {
        return $this->value;
    }
    
    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }
    
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }
    
    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    
    /**
     * Lifecycle callback to set the `createdAt` and `updatedAt` fields before inserting a new record.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }
    
    /**
     * Lifecycle callback to update the `updatedAt` field before every update.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
