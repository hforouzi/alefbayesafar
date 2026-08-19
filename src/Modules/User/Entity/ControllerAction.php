<?php
// src/Entity/ControllerAction.php
namespace App\Modules\User\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]

class ControllerAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    
    #[ORM\Column(type: 'string', length: 255)]
    private string $controller;
    
    #[ORM\Column(type: 'string', length: 255)]
    private string $action;
    
    #[ORM\ManyToMany(targetEntity: Permission::class, mappedBy: 'controllerActions')]
    private Collection $permissions;
    
    public function __construct()
    {
        $this->permissions = new ArrayCollection();
    }
    
    // Getters and setters...
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getController(): ?string
    {
        return $this->controller;
    }
    
    public function setController(string $controller): self
    {
        $this->controller = $controller;
        
        return $this;
    }
    
    public function getAction(): ?string
    {
        return $this->action;
    }
    
    public function setAction(string $action): self
    {
        $this->action = $action;
        
        return $this;
    }
    
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }
}
