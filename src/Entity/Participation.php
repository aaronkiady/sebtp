<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Liste::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Liste $adherent = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\Column(length: 20)]
    private string $statutPaiement = 'impaye';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdherent(): ?Liste
    {
        return $this->adherent;
    }

    public function setAdherent(?Liste $adherent): static
    {
        $this->adherent = $adherent;
        return $this;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getStatutPaiement(): string
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(string $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;
        return $this;
    }
    
    public function isPaye(): bool
    {
        return $this->statutPaiement === 'paye';
    }
}