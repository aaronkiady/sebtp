<?php

namespace App\Entity;

use App\Repository\DirigeantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DirigeantRepository::class)]
class Dirigeant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $president = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secretaire = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tresorier = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPresident(): ?string
    {
        return $this->president;
    }

    public function setPresident(string $president): static
    {
        $this->president = $president;

        return $this;
    }

    public function getSecretaire(): ?string
    {
        return $this->secretaire;
    }

    public function setSecretaire(?string $secretaire): static
    {
        $this->secretaire = $secretaire;

        return $this;
    }

    public function getTresorier(): ?string
    {
        return $this->tresorier;
    }

    public function setTresorier(?string $tresorier): static
    {
        $this->tresorier = $tresorier;

        return $this;
    }

     /**
     * Récupère le nom du signataire en fonction de la fonction
     */
    public function getSignataireByFonction(string $fonction): ?string
    {
        return match ($fonction) {
            'president' => $this->getPresident(),
            'secretaire' => $this->getSecretaire(),
            'tresorier' => $this->getTresorier(),
            default => $this->getPresident(),
        };
    }

    /**
     * Récupère le titre du signataire en fonction de la fonction
     */
    public function getTitreByFonction(string $fonction): string
    {
        return match ($fonction) {
            'president' => 'Le Président du SEBTP',
            'secretaire' => 'Secrétaire Exécutive du SEBTP',
            'tresorier' => 'Le Trésorier du SEBTP',
            default => 'Le Président du SEBTP',
        };
    }
}

