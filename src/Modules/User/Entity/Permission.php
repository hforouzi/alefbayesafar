<?php

namespace App\Modules\User\Entity;

use App\Modules\Default\Entity\Menu;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity()]

class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;
    
    #[ORM\ManyToMany(targetEntity: ControllerAction::class, inversedBy: 'permissions')]
    #[ORM\JoinTable(name: 'permission_controller_action')]
    private Collection $controllerActions;

    #[ORM\ManyToMany(targetEntity: Menu::class, mappedBy: 'permissions')]
    private Collection $menus;
    
    #[ORM\Column(type: 'string', length: 255)]
   
    private $route;
    public function __construct()
    {
        $this->controllerActions = new ArrayCollection();
        $this->menus = new ArrayCollection();
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
    
    public function getControllerActions(): Collection
    {
        return $this->controllerActions;
    }
    
    public function addControllerAction(ControllerAction $controllerAction): self
    {
        if (!$this->controllerActions->contains($controllerAction)) {
            $this->controllerActions[] = $controllerAction;
        }
        
        return $this;
    }
    
    public function removeControllerAction(ControllerAction $controllerAction): self
    {
        $this->controllerActions->removeElement($controllerAction);
        
        return $this;
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
        }

        return $this;
    }

    public function removeMenu(Menu $menu): self
    {
        $this->menus->removeElement($menu);

        return $this;
    }
    
    /**
     * @return mixed
     */
    public function getRoute()
    {
        return $this->route;
    }
    
    /**
     * @param mixed $route
     */
    public function setRoute($route): void
    {
        $this->route = $route;
    }
}
