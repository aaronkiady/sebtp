<?php

namespace App\Entity;

use App\Repository\ParticipationLigneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationLigneRepository::class)]
class ParticipationLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participationLignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Participation $participation = null;

    #[ORM\ManyToOne(inversedBy: 'participationLignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LigneEvenement $ligne = null;

    #[ORM\Column]
    private ?int $quantite = 1;

    #[ORM\Column]
    private ?float $montantLigne = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipation(): ?Participation
    {
        return $this->participation;
    }

    public function setParticipation(?Participation $participation): static
    {
        $this->participation = $participation;
        return $this;
    }

    public function getLigne(): ?LigneEvenement
    {
        return $this->ligne;
    }

    public function setLigne(?LigneEvenement $ligne): static
    {
        $this->ligne = $ligne;
        $this->calculerMontantLigne();
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
        $this->calculerMontantLigne();
        return $this;
    }

    public function getMontantLigne(): ?float
    {
        return $this->montantLigne;
    }

    public function setMontantLigne(?float $montantLigne): static
    {
        $this->montantLigne = $montantLigne;
        return $this;
    }

    public function calculerMontantLigne(): void
    {
        if ($this->ligne && $this->quantite) {
            $this->montantLigne = $this->ligne->getMontantUnitaire() * $this->quantite;
        } else {
            $this->montantLigne = 0;
        }
    }
}